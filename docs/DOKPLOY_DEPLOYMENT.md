# 🚀 Panduan Deploy SuratinAja di Dokploy (PaaS)

Dokploy adalah platform PaaS self-hosted modern berbasis Docker dan Traefik. SuratinAja sudah dioptimasi sepenuhnya agar dapat langsung dijalankan di Dokploy menggunakan fitur **Docker Compose Stack** dalam hitungan menit tanpa perlu konfigurasi Nginx reverse proxy manual atau certbot.

---

## 🌟 Keuntungan Deploy di Dokploy

1. **Otomatis SSL & Domain**: Traefik bawaan Dokploy otomatis mengurus sertifikat HTTPS Let's Encrypt.
2. **Web Terminal Terintegrasi**: Anda bisa menjalankan `php artisan app:create-user` atau migrasi langsung dari browser tanpa perlu SSH ke server VPS.
3. **Auto Deploy (CI/CD)**: Dukungan Git Webhook (GitHub, GitLab, atau Git kustom) sehingga saat ada `git push`, aplikasi otomatis ter-rebuild.
4. **Multi-Container Stack**: Aplikasi Laravel (`app`), antrean latar belakang (`worker`), penjadwal (`scheduler`), engine PDF (`gotenberg`), dan database (`mysql`) berjalan bersamaan dalam satu stack yang terisolasi.

---

## 📋 Langkah Demi Langkah Deploy di Dokploy

### Langkah 1: Buat Project & Service di Dashboard Dokploy

1. Buka dashboard Dokploy Anda (misal: `https://dokploy.domainanda.com`).
2. Masuk ke menu **Projects** > Klik **Create Project** (beri nama: `SuratinAja`).
3. Di dalam project, klik **Create Service** > Pilih opsi **Compose**.
4. Beri nama service: `suratinaja-stack`.

---

### Langkah 2: Hubungkan Repositori Git

1. Pada tab **General / Source** di service Compose tersebut:
   * Pilih provider Git Anda (**GitHub**, **GitLab**, atau **Git URL**).
   * Pilih repositori SuratinAja Anda.
   * Pilih branch: `main`.
2. Pada bagian **Compose Path**, isi:
   ```text
   docker-compose.dokploy.yml
   ```
   *(Atau gunakan `docker-compose.yml` standar jika Anda memilih network default)*.

---

### Langkah 3: Konfigurasi Environment Variables (Tab Environment)

Masuk ke tab **Environment** pada service Compose di Dokploy, lalu salin dan sesuaikan konfigurasi berikut:

```dotenv
# ==============================================================================
# SURATINAJA DOKPLOY PRODUCTION ENVIRONMENT
# ==============================================================================

APP_NAME="SuratinAja"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://surat.domainanda.com

# Generate kunci ini via terminal (php artisan key:generate --show) atau biarkan Dokploy generate
APP_KEY=base64:O308AMZodXAdU1Yc6QQD9ZtYIn0coNYxlF+noDl8Cro=

# Database External / Dokploy Database
DB_CONNECTION=mysql
DB_HOST=43.156.64.105
DB_PORT=20129
DB_DATABASE=suratinaja
DB_USERNAME=root
DB_PASSWORD=

# Storage, Cache & Queue
FILESYSTEM_DISK=public
QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database

# Gotenberg Engine (Konversi DOCX ke PDF)
GOTENBERG_URL=http://gotenberg:3000

# Akun Superadmin Awal (Untuk login pertama kali di hosting)
SUPERADMIN_EMAIL=admin@organisasi.id
SUPERADMIN_PASSWORD=PasswordAdminKuat123!
```

> **Catatan Penting:** Ganti `https://surat.domainanda.com` dengan domain asli yang Anda miliki, serta ubah `SUPERADMIN_EMAIL` dan `SUPERADMIN_PASSWORD`.

Klik **Save** setelah selesai.

---

### Langkah 4: Hubungkan Domain & Aktifkan HTTPS (Tab Domains)

1. Masuk ke tab **Domains** pada service Compose di Dokploy.
2. Klik **Add Domain**:
   * **Host**: `surat.domainanda.com` (pastikan A-record domain di Cloudflare/DNS Anda sudah diarahkan ke IP VPS Dokploy).
   * **Service**: Pilih container `app`.
   * **Container Port**: `8080`.
   * **Certificate**: Centang **Let's Encrypt** (HTTPS otomatis).
3. Klik **Save**.

---

### Langkah 5: Deploy Aplikasi

1. Klik tombol **Deploy** di pojok kanan atas Dokploy.
2. Dokploy akan mengunduh source code, menjalankan multi-stage build (Composer & NPM build), lalu menyalakan semua container (`app`, `worker`, `scheduler`, `gotenberg`, `mysql`).
3. Tunggu hingga status menunjukkan **Running** (indikator hijau).

---

## 🔑 Cara Akses Login Pertama Kali di Dokploy

Ada 2 cara sangat mudah untuk mengakses login tanpa perlu buka SSH terminal:

### Opsi 1: Menggunakan Web Terminal Dokploy (Rekomendasi)

1. Di dashboard Dokploy, buka service Compose `suratinaja-stack`.
2. Klik tab **Terminal** (atau buka tab **Containers** > klik ikon terminal di container `app`).
3. Dokploy akan membuka terminal interaktif langsung di browser Anda.
4. Jalankan perintah pembuatan user:
   ```bash
   php artisan app:create-user
   ```
5. Masukkan:
   * **Nama:** Misal `Ketua Umum`
   * **Email:** `admin@organisasi.id`
   * **Password:** `PasswordKuat123!`
   * **Role:** Pilih `admin` (superadmin)
   * **Workspace:** Buat baru atau pilih yang sudah ada.
6. Buka `https://surat.domainanda.com/login` di browser, lalu login dengan email dan password tersebut!

### Opsi 2: Otomatis via Seeder

Jika Anda sudah mengisi `SUPERADMIN_EMAIL` dan `SUPERADMIN_PASSWORD` pada tab **Environment**:
1. Buka tab **Terminal** container `app`.
2. Jalankan perintah seeder:
   ```bash
   php artisan db:seed --force
   ```
3. Akun Superadmin dan workspace default otomatis dibuat sesuai data di tab Environment.

---

## 🛠️ Perintah Berguna Lainnya di Web Terminal Dokploy

Semua perintah ini bisa langsung diketikkan di tab **Terminal** container `app`:

* **Reset Password jika Lupa:**
  ```bash
  php artisan app:reset-password admin@organisasi.id
  ```

* **Clear / Refresh Cache saat Update:**
  ```bash
  php artisan optimize:clear
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  ```

* **Cek Status Antrean Bulk Generate:**
  ```bash
  php artisan queue:monitor default
  ```

---

## 💾 Persistensi Data (Storage & Database)

Volume data berikut tersimpan secara persisten di host VPS Dokploy:
* `suratinaja-storage`: Menyimpan file template DOCX, kop surat, dan PDF hasil generate.
* `suratinaja-mysql`: Menyimpan seluruh data database MySQL.

Data Anda **tidak akan hilang** saat container di-restart, di-update, atau dideploy ulang.
