# ===========================================================
# Stage 1 — Base : PHP + extensions + Composer
# ===========================================================
FROM php:8.4-fpm-alpine AS base

# 1. Dépendances système + extensions PHP
# Sur certains postes Windows, l'antivirus intercepte le HTTPS Alpine (certificat non reconnu).
# On bascule temporairement les dépôts en HTTP le temps du build, puis on les rétablit.
RUN set -eux; \
    cp /etc/apk/repositories /etc/apk/repositories.bak; \
    sed -i 's|https://dl-cdn.alpinelinux.org|http://dl-cdn.alpinelinux.org|g' /etc/apk/repositories; \
    apk add --no-cache \
        ca-certificates \
        git \
        tar \
        curl \
        xz \
        icu-data-full \
        icu-libs \
        libzip \
        freetype \
        libjpeg-turbo \
        libpng \
        oniguruma \
        acl \
    && update-ca-certificates \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        linux-headers \
        icu-dev \
        libzip-dev \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        intl \
        opcache \
        pdo_mysql \
        zip \
        gd \
        mbstring \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && apk del --no-network .build-deps \
    && mv /etc/apk/repositories.bak /etc/apk/repositories \
    && rm -rf /tmp/pear /root/.pearrc /var/cache/apk/*

# 2. Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 3. Config PHP personnalisée
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh
WORKDIR /app
COPY composer.json composer.lock ./

# ===========================================================
# Stage 2 — Dev (celui qu'on utilise au quotidien)
# ===========================================================
FROM base AS dev
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini
RUN apk add --no-cache --virtual .xdebug-deps $PHPIZE_DEPS linux-headers \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del --no-network .xdebug-deps \
    && rm -rf /tmp/pear /root/.pearrc /var/cache/apk/*
RUN composer install --no-scripts --no-interaction --prefer-dist
COPY . .
RUN composer run-script post-install-cmd || true
RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data var
# Entrypoint en root pour chown des volumes ; PHP-FPM bascule les workers en www-data.
USER root
EXPOSE 9000
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]

# ===========================================================
# Stage 3 — Prod (pour le déploiement)
# ===========================================================
FROM base AS prod
ENV APP_ENV=prod
RUN composer install --no-dev --no-scripts --optimize-autoloader --prefer-dist
COPY . .
RUN composer run-script post-install-cmd || true \
    && php bin/console tailwind:build --minify \
    && php bin/console asset-map:compile \
    && php bin/console cache:warmup
RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data var \
    && cp -a /app/public /app/public.dist

# Strip build tools and optional CLI utilities in production to eliminate their CVEs
USER root
RUN apk del --no-network git tar curl xz && rm -rf /var/cache/apk/*

# Entrypoint en root pour chown du volume public/ ; PHP-FPM bascule les workers en www-data.
EXPOSE 9000
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]