#!/usr/bin/env bash
# Déploiement production Docker — à exécuter sur le VPS depuis la racine du projet.
#
# Usage :
#   ./bin/deploy-docker.sh              # git pull + rebuild + redémarrage prod
#   ./bin/deploy-docker.sh --no-pull    # sans git pull
#   ./bin/deploy-docker.sh --first-install
#
# Prérequis :
#   - Docker + Docker Compose
#   - .env.docker.local (copier depuis .env.docker.local.dist)
#   - Nginx sur l'hôte en reverse proxy vers http://127.0.0.1:8080 (HTTPS via Certbot)
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_DIR"

ENV_FILE=".env.docker.local"
COMPOSE_PROD=(docker compose --env-file "$ENV_FILE" -f compose.prod.yaml)
HEALTH_URL="${DEPLOY_HEALTH_URL:-http://127.0.0.1:8080/}"
SKIP_PULL=0
FIRST_INSTALL=0

usage() {
  cat <<'EOF'
Usage : ./bin/deploy-docker.sh [options]

Options :
  --no-pull         Ne pas exécuter git pull
  --first-install   Crée .env.docker.local depuis le modèle si absent, puis déploie
  -h, --help        Affiche cette aide

Variables optionnelles :
  DEPLOY_HEALTH_URL   URL de vérification HTTP (défaut : http://127.0.0.1:8080/)
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --no-pull) SKIP_PULL=1 ;;
    --first-install) FIRST_INSTALL=1 ;;
    -h|--help) usage; exit 0 ;;
    *)
      echo "Option inconnue : $1" >&2
      usage
      exit 1
      ;;
  esac
  shift
done

require_command() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "Commande requise introuvable : $1" >&2
    exit 1
  fi
}

require_command docker
if ! docker compose version >/dev/null 2>&1; then
  echo "Docker Compose (plugin) est requis." >&2
  exit 1
fi

if [[ ! -f "$ENV_FILE" ]]; then
  if [[ "$FIRST_INSTALL" -eq 1 ]]; then
    cp .env.docker.local.dist "$ENV_FILE"
    echo "Créé $ENV_FILE — éditez-le (secrets, DEFAULT_URI, SMTP) puis relancez sans --first-install."
    exit 0
  fi
  echo "Fichier manquant : $ENV_FILE" >&2
  echo "Copiez .env.docker.local.dist vers .env.docker.local et configurez les secrets." >&2
  exit 1
fi

validate_env() {
  local errors=0

  if ! grep -q '^APP_ENV=prod' "$ENV_FILE"; then
    echo "Erreur : APP_ENV doit valoir prod dans $ENV_FILE" >&2
    errors=1
  fi

  if ! grep -q '^APP_DEBUG=0' "$ENV_FILE"; then
    echo "Erreur : APP_DEBUG doit valoir 0 dans $ENV_FILE" >&2
    errors=1
  fi

  if grep -qE '^APP_SECRET=(change_me|changeme|change_me_generate)' "$ENV_FILE"; then
    echo "Erreur : APP_SECRET n'est pas configuré dans $ENV_FILE" >&2
    errors=1
  fi

  if grep -qE '^(MYSQL_ROOT_PASSWORD|MYSQL_PASSWORD)=changeme' "$ENV_FILE"; then
    echo "Erreur : mots de passe MySQL par défaut détectés dans $ENV_FILE" >&2
    errors=1
  fi

  if grep -q '^MAILER_DSN=smtp://mailer:1025' "$ENV_FILE"; then
    echo "Attention : MAILER_DSN pointe vers Mailpit (OK en test, pas pour un vrai envoi SMTP)." >&2
  fi

  if [[ "$errors" -eq 1 ]]; then
    exit 1
  fi
}

wait_for_http() {
  local url="$1"
  local attempts="${2:-30}"
  local i

  if ! command -v curl >/dev/null 2>&1; then
    echo "curl absent — vérification HTTP ignorée."
    return 0
  fi

  echo "==> Vérification HTTP ($url)"
  for ((i = 1; i <= attempts; i++)); do
    local code
    code="$(curl -s -o /dev/null -w '%{http_code}' "$url" || true)"
    if [[ "$code" =~ ^(200|301|302|303|307|308)$ ]]; then
      echo "    HTTP $code"
      return 0
    fi
    sleep 2
  done

  echo "Échec : pas de réponse HTTP valide après ${attempts} tentatives." >&2
  echo "Consultez les logs : docker compose --env-file $ENV_FILE -f compose.prod.yaml logs --tail=80" >&2
  exit 1
}

validate_env

if [[ "$SKIP_PULL" -eq 0 ]]; then
  require_command git
  echo "==> Git pull"
  git pull --ff-only
else
  echo "==> Git pull ignoré (--no-pull)"
fi

echo "==> Arrêt éventuel de la stack dev"
docker compose down 2>/dev/null || true

echo "==> Build et démarrage stack prod"
"${COMPOSE_PROD[@]}" up -d --build --remove-orphans

echo "==> Attente santé MySQL"
for _ in $(seq 1 30); do
  if "${COMPOSE_PROD[@]}" ps database 2>/dev/null | grep -q '(healthy)'; then
    break
  fi
  sleep 2
done

if ! "${COMPOSE_PROD[@]}" ps database 2>/dev/null | grep -q '(healthy)'; then
  echo "Attention : MySQL n'est pas encore healthy. Vérifiez les logs database." >&2
fi

wait_for_http "$HEALTH_URL" 45

echo "==> État des conteneurs"
"${COMPOSE_PROD[@]}" ps

cat <<EOF

OK — déploiement Docker terminé.

Application locale (reverse proxy Nginx) : http://127.0.0.1:8080
Ne chargez jamais les fixtures en production.

Commandes utiles :
  docker compose --env-file $ENV_FILE -f compose.prod.yaml logs -f
  docker compose --env-file $ENV_FILE -f compose.prod.yaml exec php php bin/console mailer:test votre@email.com
EOF
