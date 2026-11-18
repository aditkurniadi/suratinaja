# ==============================================================================
# Stage 1 — Build frontend assets (Tailwind v4 + Vite)
# ==============================================================================
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json* vite.config.js ./
RUN npm install
COPY resources ./resources
COPY app ./app
RUN npm run build

# ==============================================================================
# Stage 2 — Install PHP Composer dependencies
# ==============================================================================
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# ==============================================================================
# Stage 3 — Production Runtime (PHP 8.3 FPM + Nginx via ServerSideUp)
# ==============================================================================
FROM serversideup/php:8.3-fpm-nginx-alpine AS runtime

USER root
RUN install-php-extensions gd intl zip pdo_mysql bcmath opcache \
    && apk add --no-cache mysql-client

WORKDIR /var/www/html

COPY --chown=www-data:www-data . /var/www/html
COPY --from=vendor --chown=www-data:www-data /app/vendor /var/www/html/vendor
COPY --from=assets --chown=www-data:www-data /app/public/build /var/www/html/public/build

ENV PHP_OPCACHE_ENABLE=1 \
    SSL_MODE=off \
    AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION=true \
    AUTORUN_LARAVEL_STORAGE_LINK=true

USER www-data

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8080/up || exit 1
