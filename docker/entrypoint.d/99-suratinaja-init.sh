#!/bin/sh
set -e

# Pastikan direktori storage & framework cache selalu ada dan dimiliki www-data
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/storage/app/public \
         /var/www/html/bootstrap/cache

# Jalankan migrasi, sync permission, reset cache, dan optimasi hanya di container app
if [ "${AUTORUN_LARAVEL_MIGRATION:-false}" = "true" ] || [ "${CONTAINER_ROLE:-app}" = "app" ]; then
    echo "==> [SuratinAja] Menjalankan migrasi database..."
    php /var/www/html/artisan migrate --force || true

    echo "==> [SuratinAja] Menyinkronkan akun Super Admin dari environment..."
    php /var/www/html/artisan app:sync-superadmin || true

    echo "==> [SuratinAja] Menyinkronkan permission & role Spatie (idempotent)..."
    php /var/www/html/artisan db:seed --class=RoleAndPermissionSeeder --force || true

    if [ "${DEMO_MODE_ENABLED:-false}" = "true" ]; then
        echo "==> [SuratinAja] Mode Demo aktif: Menjalankan DemoWorkspaceSeeder (idempotent)..."
        php /var/www/html/artisan db:seed --class=DemoWorkspaceSeeder --force || true
    fi

    echo "==> [SuratinAja] Mereset cache permission Spatie..."
    php /var/www/html/artisan permission:cache-reset || true

    echo "==> [SuratinAja] Mengoptimasi konfigurasi dan route cache..."
    php /var/www/html/artisan optimize:clear || true
    php /var/www/html/artisan optimize || true
fi
