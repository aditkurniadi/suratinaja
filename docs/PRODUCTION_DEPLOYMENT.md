# Panduan Deployment Production Docker & Akses Login — SuratinAja

Dokumen ini menjelaskan tata cara deployment aplikasi **SuratinAja** ke server production (VPS / Cloud Server) menggunakan Docker & Docker Compose, serta panduan lengkap mengenai **akses login pertama kali di lingkungan hosting**.

---

## 1. Arsitektur Layanan Docker Production

Sistem SuratinAja terdiri dari 5 container yang saling terintegrasi dalam jaringan Docker:

```
[ Domain / Pengguna ]
         │ (Port 80 / 443 via Reverse Proxy / Cloudflare)
         ▼
 ┌───────────────┐        ┌───────────────┐
 │   Container   │ ─────> │   Container   │
 │      app      │ <───── │   gotenberg   │ (Konversi DOCX ke PDF)
 │ (Nginx + PHP) │        │   (Port 3000) │
 └───────┬───────┘        └───────────────┘
         │
         ├───> [ MySQL 8.0 Database Container ] (Penyimpanan Data)
         │
 ┌───────┴───────┐        ┌───────────────┐
 │   Container   │        │   Container   │
 │    worker     │        │   scheduler   │
 │ (Antrean Bulk)│        │ (Cron Pembersih)
 └───────────────┘        └───────────────┘
```

1. **`app`**: Web server utama berbasis `serversideup/php:8.3-fpm-nginx-alpine` (menggabungkan Nginx + PHP-FPM dengan memory limit 512M dan batas upload file 64MB).
2. **`worker`**: Background worker untuk memproses pencetakan surat masal (*Bulk Generation*) tanpa membebani web server.
3. **`scheduler`**: Cron runner otomatis untuk membersihkan file sementara dan cache.
4. **`gotenberg`**: Microservice Gotenberg 8 untuk pratinjau dokumen PDF instan berakurasi tinggi (A4 margin normal).
5. **`mysql`**: Database server MySQL 8.0 dengan volume persisten (`suratinaja-mysql`).

---

## 2. Akses Login Waktu Hosting (Cara Masuk Pertama Kali)

Saat aplikasi pertama kali dideploy di hosting, ada **3 cara praktis dan aman** untuk mendapatkan akun login:

### Cara 1: Mengatur Akun Super Admin Otomatis melalui `.env` (Paling Praktis)
Sebelum menjalankan container, buka file `.env` di server hosting dan tentukan email serta password yang Anda inginkan:
```ini
SUPERADMIN_EMAIL=admin@organisasi.id
SUPERADMIN_PASSWORD=PasswordKuatAnda123!
```
Saat container pertama kali dijalankan dan database diinisialisasi (`php artisan db:seed`), sistem otomatis membuat akun Super Admin tersebut. Anda dapat langsung membuka website dan login dengan email serta password tersebut.

### Cara 2: Membuat Akun Baru via Terminal SSH Server (Paling Fleksibel)
Anda dapat membuat akun admin baru kapan saja langsung dari terminal server dengan menjalankan perintah interaktif:
```bash
docker compose exec app php artisan app:create-user
```
Terminal akan memandu Anda:
1. Masukkan Alamat Email (misal: `ketua@himatris.id`).
2. Masukkan Nama Lengkap.
3. Masukkan Password.
4. Pilih Workspace/Organisasi (atau buat organisasi baru).
5. Pilih Peran: **Admin**, **Sekretaris**, atau **Ketua**.

Atau dengan perintah cepat sekali jalan:
```bash
docker compose exec app php artisan app:create-user admin@organisasi.id --name="Raka Admin" --password="Password123!" --role=admin --super-admin
```

### Cara 3: Mengundang Anggota Melalui Dashboard Web
Setelah Anda login sebagai Admin:
1. Masuk ke menu **Konfigurasi Workspace** &rarr; **Anggota & Akses** (`/settings/members`).
2. Klik tombol **"+ Undang Anggota Baru"**.
3. Pilih peran (Sekretaris / Ketua / Admin).
4. Salin tautan undangan dan kirimkan ke anggota tim Anda. Mereka cukup membuka tautan tersebut untuk mendaftarkan akun masing-masing.

### Jika Lupa Password Akun di Server:
Cukup jalankan perintah reset password:
```bash
docker compose exec app php artisan app:reset-password email_anda@domain.com
```

---

## 3. Langkah-Langkah Deployment ke Server VPS (Step-by-Step)

### Kebutuhan Minimum Server
- OS: Ubuntu 22.04 / 24.04 LTS atau Debian 12
- Spesifikasi: Minimal 1 vCPU, 2 GB RAM, 20 GB Disk
- Software: Docker & Docker Compose v2 terinstall

### Langkah 1: Clone Repositori ke Server
```bash
git clone https://github.com/aditkurniadi/suratinaja.git /var/www/suratinaja
cd /var/www/suratinaja
```

### Langkah 2: Buat File Konfigurasi `.env`
Salin template konfigurasi production yang sudah disiapkan:
```bash
cp .env.production.example .env
nano .env
```
Sesuaikan variabel penting:
- `APP_URL=https://surat.domain-anda.com`
- `DB_PASSWORD=PasswordDatabaseAcakYangAman`
- `SUPERADMIN_EMAIL=admin@domain-anda.com`
- `SUPERADMIN_PASSWORD=PasswordLoginAdminAnda`

### Langkah 3: Generate Application Key
Jalankan perintah ini satu kali untuk membuat kunci enkripsi Laravel:
```bash
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```
Salin outputnya ke dalam baris `APP_KEY=` di file `.env`.

### Langkah 4: Build dan Jalankan Container Docker
```bash
docker compose up -d --build
```
Docker akan:
1. Melakukan build multi-stage (Node.js compile asset Tailwind & Vite, Composer install vendor).
2. Menyalakan database MySQL, Gotenberg PDF engine, PHP-FPM Web, Worker, dan Scheduler.
3. Secara otomatis menjalankan migrasi tabel database dan symlink storage file.

### Langkah 5: Jalankan Seeder Awal (Jika Pertama Kali)
```bash
docker compose exec app php artisan db:seed
```

### Langkah 6: Konfigurasi Domain & SSL (HTTPS)
Arahkan domain/subdomain Anda (misal `surat.himatris.org`) ke IP server VPS Anda.
Gunakan Nginx Proxy Manager, Caddy, atau Nginx host dengan Certbot Let's Encrypt untuk mengarahkan trafik HTTPS port 443 ke port aplikasi Docker (`8000` atau `8080`).

Contoh konfigurasi reverse proxy Nginx host:
```nginx
server {
    server_name surat.himatris.org;

    client_max_body_size 64M;

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

---

## 4. Perintah Pemeliharaan Harian (Cheat Sheet)

| Kebutuhan | Perintah |
|---|---|
| **Cek status semua container** | `docker compose ps` |
| **Lihat log web server real-time** | `docker compose logs -f app` |
| **Lihat log antrean bulk generation** | `docker compose logs -f worker` |
| **Restart seluruh layanan** | `docker compose restart` |
| **Jalankan migrasi database baru** | `docker compose exec app php artisan migrate --force` |
| **Clear cache aplikasi** | `docker compose exec app php artisan optimize:clear` |
| **Buat backup database** | `docker compose exec app php artisan app:backup-database` |
| **Buat akun pengguna baru** | `docker compose exec app php artisan app:create-user` |
| **Reset password pengguna** | `docker compose exec app php artisan app:reset-password <email>` |
