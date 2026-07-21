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

mkdir -p \
    var/cache \
    var/log \
    public/images/articles \
    public/images/board-games \
    public/images/carousel \
    public/images/cards \
    public/images/forum \
    public/images/hero

# L'entrypoint tourne en root pour pouvoir chown le volume partagé public/.
# PHP-FPM garde ensuite ses workers sous www-data.
if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data var public 2>/dev/null || true
    chmod -R u+rwX var public/images 2>/dev/null || true
fi

if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    echo "==> Exécution des migrations Doctrine..."
    if [ "$(id -u)" = "0" ]; then
        su -s /bin/sh www-data -c 'php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration'
    else
        php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
    fi
fi

exec docker-php-entrypoint "$@"
