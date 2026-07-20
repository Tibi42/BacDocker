#!/usr/bin/env bash
# Docker Compose helpers for La Boite Chimere.
# Usage : ./bin/docker.sh <command>
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if ! command -v docker >/dev/null 2>&1; then
  echo "Docker is not installed or not accessible." >&2
  exit 1
fi

COMPOSE=(docker compose)
PHP=(docker compose exec php)
COMPOSE_PROD=(docker compose --env-file .env.docker.local -f compose.prod.yaml)

usage() {
  cat <<'EOF'
Usage : ./bin/docker.sh <command>

Commands :
  setup       First install (env + build + migrations + tailwind + cache)
  up          Start containers (dev)
  down        Stop containers (dev)
  build       Rebuild PHP image (dev)
  shell       Shell inside PHP container
  migrate     Run Doctrine migrations
  fixtures    Load fixture users (superadmin / admin / user)
  test        PHPUnit
  tailwind    Compile Tailwind CSS
  cache       Clear Symfony cache
  logs        Follow logs
  mail-test   Send a test email to Mailpit
  prod        Start production Docker stack
  prod-down   Stop production Docker stack
EOF
}

cmd="${1:-}"
case "$cmd" in
  setup)
    if [[ ! -f .env.docker.local ]]; then
      cp .env.docker.local.dist .env.docker.local
      echo "Created .env.docker.local - edit it if needed."
    fi

    "${COMPOSE[@]}" up -d --build
    "${PHP[@]}" php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
    "${PHP[@]}" php bin/console load-fixtures
    "${PHP[@]}" php bin/console tailwind:build
    "${PHP[@]}" php bin/console cache:clear
    echo "OK - http://localhost:8080 (Mailpit : http://localhost:8025)"
    echo "Login superadmin : boiteachimere@guillaumepecquet.ovh / DevSuperAdmin!12"
    ;;

  up) "${COMPOSE[@]}" up -d ;;
  down) "${COMPOSE[@]}" down ;;
  build) "${COMPOSE[@]}" build --no-cache php ;;
  shell) "${PHP[@]}" sh ;;

  migrate) "${PHP[@]}" php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration ;;
  fixtures) "${PHP[@]}" php bin/console load-fixtures ;;
  test) "${PHP[@]}" php bin/phpunit ;;
  tailwind) "${PHP[@]}" php bin/console tailwind:build ;;
  cache) "${PHP[@]}" php bin/console cache:clear ;;
  logs) "${COMPOSE[@]}" logs -f ;;
  mail-test) "${PHP[@]}" php bin/console mailer:test boiteachimere@guillaumepecquet.ovh --subject="Test Docker Mailpit" --body="Si vous voyez ceci dans Mailpit, le mailer fonctionne." ;;

  prod)
    if [[ ! -f .env.docker.local ]]; then
      echo "Missing .env.docker.local — copy from .env.docker.local.dist first." >&2
      exit 1
    fi
    docker compose down 2>/dev/null || true
    "${COMPOSE_PROD[@]}" up -d --build
    echo "OK prod — http://localhost:8080 (Mailpit : http://localhost:8025)"
    ;;
  prod-down)
    "${COMPOSE_PROD[@]}" down
    ;;

  ""|-h|--help|help) usage ;;
  *)
    echo "Unknown command: $cmd" >&2
    usage
    exit 1
    ;;
esac
