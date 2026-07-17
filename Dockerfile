# ===========================================================
# Stage 1 — Base : PHP + extensions + Composer
# ===========================================================
FROM php:8.4-fpm-alpine AS base

# 1. Update and install runtime dependencies AND build dependencies
RUN apk update && apk upgrade --no-cache \
    && apk add --no-cache --upgrade --repository=https://dl-cdn.alpinelinux.org/alpine/edge/main/ git tar curl xz \
    && apk add --no-cache \
        icu-data-full \
        icu-libs \
        libzip \
        freetype \
        libjpeg-turbo \
        libpng \
        oniguruma \
        acl \
    && apk add --no-cache --virtual .build-deps \
        icu-dev \
        libzip-dev \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        oniguruma-dev \
        autoconf g++ gcc make linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        intl \
        opcache \
        pdo_mysql \
        zip \
        gd \
        mbstring \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && apk del --no-network .build-deps \
    && rm -rf /tmp/pear ~/.pearrc

# 2. Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 3. Config PHP personnalisée
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini
WORKDIR /app
COPY composer.json composer.lock ./

# ===========================================================
# Stage 2 — Dev (celui qu'on utilise au quotidien)
# ===========================================================
FROM base AS dev
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS linux-headers \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del --no-network .build-deps \
    && rm -rf /tmp/pear ~/.pearrc
RUN composer install --no-scripts --no-interaction --prefer-dist
COPY . .
RUN composer run-script post-install-cmd || true
RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data var
USER www-data
EXPOSE 9000
CMD ["php-fpm"]

# ===========================================================
# Stage 3 — Prod (pour le déploiement)
# ===========================================================
FROM base AS prod
ENV APP_ENV=prod
RUN composer install --no-dev --no-scripts --optimize-autoloader --prefer-dist
COPY . .
RUN composer run-script post-install-cmd || true \
    && php bin/console cache:warmup
RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data var

# Strip build tools and optional CLI utilities in production to eliminate their CVEs
USER root
RUN apk del --no-network git tar curl xz && rm -rf /var/cache/apk/*
USER www-data

EXPOSE 9000
CMD ["php-fpm"]