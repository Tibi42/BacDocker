#!/usr/bin/env bash
# Mise à jour production — à exécuter sur le VPS depuis la racine du projet.
# Usage : ./bin/deploy.sh
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_DIR"

if [[ "${APP_ENV:-}" == "prod" ]] || grep -q '^APP_ENV=prod' .env.local 2>/dev/null; then
  :
else
  echo "Attention : .env.local ne semble pas en APP_ENV=prod."
  read -r -p "Continuer quand même ? [y/N] " confirm
  [[ "$confirm" =~ ^[yY]$ ]] || exit 1
fi

echo "==> Git pull"
git pull --ff-only

echo "==> Composer (no-dev)"
composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction

echo "==> Migrations"
php bin/console doctrine:migrations:migrate --no-interaction

echo "==> Tailwind"
php bin/console tailwind:build --minify

echo "==> AssetMapper"
php bin/console asset-map:compile

echo "==> Cache"
php bin/console cache:clear
php bin/console cache:warmup

echo "==> Permissions var/"
if id www-data &>/dev/null; then
  sudo chown -R www-data:www-data var
  sudo chmod -R ug+rwX var
fi

echo "OK — déploiement terminé."
