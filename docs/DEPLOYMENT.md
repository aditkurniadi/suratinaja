# Panduan Deployment SuratinAja (Dokploy + Docker)

## 1. Arsitektur Layanan (`docker-compose.yml`)

| Service | Peran | Image / Build |
|:---|:---|:---|
| `app` | Web server + Livewire + REST API (`/api/v1`) + healthcheck `/up` | Multi-stage `Dockerfile` (`serversideup/php:8.3-fpm-nginx-alpine`) |
| `worker` | Queue worker untuk `GenerateBulkLetterJob` (`php artisan queue:work`) | Image yang sama dengan `app` |
| `scheduler` | Cron Laravel (`php artisan schedule:work`) termasuk `backup:database` pukul 02:00 | Image yang sama dengan `app` |
| `gotenberg` | Konversi `.docx` → PDF untuk fitur Pratinjau PDF di browser | `gotenberg/gotenberg:8` (internal port `3000`) |
| `mysql` | Database relasional MySQL 8.0 | `mysql:8.0` |

## 2. Langkah Deploy di Dokploy

1. Buka dashboard **Dokploy** → pilih **Project** → klik **Create Service → Compose**.
2. Hubungkan repositori Git SuratinAja dan arahkan Compose Path ke `./docker-compose.yml`.
3. Pada tab **Environment**, isi variabel produksi:
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_KEY=base64:...
   APP_URL=https://surat.organisasi.ac.id
   DB_DATABASE=suratinaja
   DB_USERNAME=suratinaja
   DB_PASSWORD=<password-kuat>
   DB_ROOT_PASSWORD=<root-password-kuat>
   SUPERADMIN_EMAIL=superadmin@organisasi.ac.id
   SUPERADMIN_PASSWORD=<password-superadmin>
   ```
4. Pada tab **Domains**, tambahkan domain ke service `app` dengan port internal `8080` dan aktifkan HTTPS Let's Encrypt.
5. Klik **Deploy**. Container `app` otomatis menjalankan `php artisan migrate --force` saat startup.

## 3. Backup Database Harian (PRD §5)

1. **Via Dokploy Database Backups (Direkomendasikan):** buka service database di Dokploy → tab **Backups** → pilih S3/Storage Destination dan jadwal cron `0 2 * * *`.
2. **Via Scheduler Internal:** container `scheduler` menjalankan `php artisan backup:database` setiap hari pukul `02:00` dan menyimpan dump SQL ke volume `suratinaja-storage` (`storage/app/private/backups/backup-*.sql`).
3. **Restore manual:**
   ```bash
   docker compose exec -T mysql mysql -uroot -p"$DB_ROOT_PASSWORD" "$DB_DATABASE" < backup.sql
   ```
