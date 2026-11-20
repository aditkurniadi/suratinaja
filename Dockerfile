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
    --ignore-platform-reqs \
    --optimize-autoloader

# ==============================================================================
# Stage 3 — Production Runtime (PHP 8.3 FPM + Nginx via ServerSideUp)
# ==============================================================================
FROM serversideup/php:8.3-fpm-nginx-alpine AS runtime

USER root

# Install PHP extensions dan system packages (mysql-client, qpdf, poppler-utils)
RUN install-php-extensions gd intl zip pdo_mysql bcmath opcache \
    && apk add --no-cache mysql-client qpdf poppler-utils

# Konfigurasi batas upload dan eksekusi PHP produksi
RUN echo "upload_max_filesize=64M" > /usr/local/etc/php/conf.d/suratinaja-limits.ini \
    && echo "post_max_size=64M" >> /usr/local/etc/php/conf.d/suratinaja-limits.ini \
    && echo "memory_limit=512M" >> /usr/local/etc/php/conf.d/suratinaja-limits.ini \
    && echo "max_execution_time=180" >> /usr/local/etc/php/conf.d/suratinaja-limits.ini

# Konfigurasi drop-in Nginx (timeout & body size)
COPY docker/nginx-limits.conf /etc/nginx/server-opts.d/suratinaja-limits.conf

# Script startup otomatis untuk migrasi & reset cache permission
COPY docker/entrypoint.d/99-suratinaja-init.sh /etc/entrypoint.d/99-suratinaja-init.sh
RUN chmod +x /etc/entrypoint.d/99-suratinaja-init.sh

WORKDIR /var/www/html

COPY --chown=www-data:www-data . /var/www/html
COPY --from=vendor --chown=www-data:www-data /app/vendor /var/www/html/vendor
COPY --from=assets --chown=www-data:www-data /app/public/build /var/www/html/public/build

ENV PHP_OPCACHE_ENABLE=1 \
    SSL_MODE=off \
    AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION=false \
    AUTORUN_LARAVEL_STORAGE_LINK=true \
    PHP_UPLOAD_MAX_FILE_SIZE=64M \
    PHP_POST_MAX_SIZE=64M \
    NGINX_MAX_BODY_SIZE=64M \
    PHP_MEMORY_LIMIT=512M \
    PHP_MAX_EXECUTION_TIME=180 \
    NGINX_FASTCGI_READ_TIMEOUT=180s \
    QPDF_BINARY=/usr/bin/qpdf

USER www-data

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8080/up || exit 1
