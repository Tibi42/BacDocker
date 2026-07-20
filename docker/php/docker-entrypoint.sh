#!/bin/sh
set -e

cd /app

# En prod Docker, le volume partagé public/ peut être vide au premier démarrage :
# on restaure alors les assets compilés depuis l'image.
if [ -d /app/public.dist ]; then
    mkdir -p /app/public
    if [ ! -f /app/public/index.php ]; then
        echo "==> Initialisation de /app/public depuis l'image..."
        cp -a /app/public.dist/. /app/public/
    fi
fi

if [ -d var ]; then
    chown -R www-data:www-data var 2>/dev/null || true
fi

if [ -d public ]; then
    chown -R www-data:www-data public 2>/dev/null || true
fi

if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    echo "==> Exécution des migrations Doctrine..."
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
fi

exec docker-php-entrypoint "$@"
