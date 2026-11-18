# SuratinAja

Aplikasi web manajemen persuratan multi-workspace untuk organisasi kampus dan kepanitiaan (HIMATRIS, OASIS) dengan pipeline template `.docx` (`phpoffice/phpword`), penomoran otomatis anti-bentrok, bulk generation multi-halaman, audit revisi (`Hangus`), dan pratinjau PDF — dibangun di atas **TALL Stack** (Laravel 12, Livewire 3, Alpine.js, Tailwind CSS v4) dengan bahasa visual **Dokploy** (shadcn/ui *zinc*, light default + dark mode toggle).

## Akun Demo (Hasil `php artisan db:seed`)

Semua akun menggunakan kata sandi: `password`

| Peran | Email | Hak Akses |
|:---|:---|:---|
| **Super Admin** | `superadmin@suratinaja.test` | Kelola Master Template Global + Admin HIMATRIS |
| **Admin Workspace** | `admin@himatris.org` | Konfigurasi Kop Surat, Pola Nomor, Anggota (HIMATRIS & OASIS) |
| **Sekretaris** | `sekretaris@himatris.org` | Buat/Edit Draft, Ajukan TTD, Tandai Selesai, Bulk, Revisi, Duplikasi (HIMATRIS & OASIS) |
| **Ketua** | `ketua@himatris.org` | Monitoring Read-Only Daftar, Detail, Riwayat & Pratinjau PDF (HIMATRIS) |

## Menjalankan Secara Lokal (Laragon / PHP 8.3+)

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

Untuk mengaktifkan pratinjau PDF di browser secara lokal, jalankan container Gotenberg:

```bash
docker run -d --name suratinaja-gotenberg -p 3000:3000 gotenberg/gotenberg:8
```

## Pengujian Otomatis

```bash
php artisan test
```

## Dokumentasi

- [docs/PRD.md](docs/PRD.md) — Product Requirements Document
- [docs/DESIGN.md](docs/DESIGN.md) — Dokploy Design System & Tokens
- [docs/FEATURES.md](docs/FEATURES.md) — Spesifikasi Modul & Acceptance Criteria
- [docs/DATABASE.md](docs/DATABASE.md) — Skema Database & Aturan Transaksi
- [docs/API.md](docs/API.md) — Kontrak REST API v1
- [docs/DECISIONS.md](docs/DECISIONS.md) — Rekaman Keputusan Produk (DEC-001..005)
- [docs/TASKS.md](docs/TASKS.md) — Backlog & Traceability
- [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) — Panduan Deploy Dokploy & Docker Compose
