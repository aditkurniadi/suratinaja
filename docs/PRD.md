# PRD: SuratinAja

## 1. Executive Summary & Product Vision

SuratinAja adalah aplikasi web manajemen persuratan multi-workspace untuk organisasi kampus dan kepanitiaan (HIMATRIS, OASIS). Visi produk: menggantikan alur manual Word + Google Sheets dengan satu pipeline terpadu — dari template `.docx`, penomoran otomatis, hingga generate massal — tanpa admin panel berat, dibangun di atas TALL stack murni.

Nilai inti: presisi dokumen `.docx` via `phpoffice/phpword`, isolasi data per workspace, dan integritas riwayat nomor surat.

## 2. Problem Statement & Target Users

| Masalah | Dampak |
|:---|:---|
| Dua platform terpisah (Word + Sheets) | Tracking nomor rawan bentrok |
| Copy-paste massal 20+ penerima | Human error nama/gelar/nomor |
| Format dokumen tidak konsisten | Layout rusak saat revisi |
| Kop surat diganti manual per file | Inefisiensi kegiatan terprogram vs non-terprogram |
| Revisi surat bertanda tangan | Nomor lama hangus tanpa jejak |

**Target Users:** Sekretaris organisasi/kepanitiaan, Ketua (read-only monitoring), Admin workspace.

## 3. System Scope & User Roles

Multi-workspace dengan isolasi data penuh. Setiap user dapat tergabung di beberapa workspace dengan peran berbeda.

| Permission | Admin Workspace | Sekretaris | Ketua |
|:---|:---|:---|:---|
| Kelola workspace & anggota | ✓ | ✗ | ✗ |
| Konfigurasi kop surat & pola nomor | ✓ | ✗ | ✗ |
| Upload template `.docx` | ✓ | ✓ | ✗ |
| Buat & edit surat | ✓ | ✓ | ✗ |
| Generate nomor surat | ✓ | ✓ | ✗ |
| Bulk generation | ✓ | ✓ | ✗ |
| Ubah status surat (DEC-004) | ✓ | ✓ | ✗ |
| Manual assign nomor revisi (DEC-005) | ✓ | ✓ | ✗ |
| Lihat daftar & riwayat surat | ✓ | ✓ | ✓ |

## 4. Functional Requirements

### User-Facing

- **FR-01 (DEC-001):** Workspace dapat memilih template global (read-only) atau mengunggah template `.docx` mandiri. Template global tidak dapat diedit, hanya diduplikasi.
- **FR-02:** Sistem mem-parse variabel `${...}` dari master template `.docx` dan merender form Livewire dinamis sesuai tipe variabel (teks, tanggal, repeater untuk tabel lampiran).
- **FR-03:** Builder Kop Surat per workspace: logo kiri, logo kanan, teks header, alamat. Kop menempel otomatis pada setiap surat yang digenerate.
- **FR-04 (DEC-002):** Generator nomor dengan pola kustom per workspace (token: nomor urut, kode, bulan, tahun). Pola disimpan sebagai string template.
- **FR-05 (DEC-003):** Bulk generation mendukung input manual (array nama penerima) dan upload file (CSV/XLSX). Output: satu file `.docx` utuh berisi puluhan halaman dengan nomor berurutan.
- **FR-06 (DEC-004):** Alur status surat: `Draft` → `Menunggu TTD` → `Selesai`. Transisi ke `Selesai` dilakukan manual oleh sekretaris setelah tanda tangan basah.
- **FR-07 (DEC-005):** Saat revisi surat bernomor, sekretaris memilih: pakai nomor baru atau tetap. Wajib mengisi alasan revisi. Nomor lama berstatus `Hangus` bila diganti.
- **FR-08:** Daftar surat per workspace dengan filter status dan pencarian nomor/perihal.

### Admin-Facing

- **FR-09:** Manajemen anggota workspace (invite, assign peran, revoke).
- **FR-10:** Konfigurasi pola penomoran dan builder kop surat per workspace.
- **FR-11:** Kelola master template global (upload, versi, deprecate).

## 5. Non-Functional Requirements

| Aspek | Target |
|:---|:---|
| Waktu generate 1 surat | < 3 detik |
| Waktu bulk generate 50 penerima | < 30 detik |
| Concurrent users per workspace | ≥ 10 |
| Isolasi data antar workspace | Wajib (query scope) |
| Ukuran file template maks | TBD |
| Retensi file `.docx` hasil generate | TBD |
| Backup database | Harian |

## 6. Technology Stack & Rationale

| Component | Technology | Why |
|:---|:---|:---|
| Frontend | Livewire 3 + Alpine + Tailwind v4 | Reaktif tanpa SPA; form dinamis dari parsing template |
| Backend | Laravel 12 (PHP 8.3+) | Ekosistem PHPWord native, queue untuk bulk |
| Database | MySQL 8.0+ | Relasional untuk workspace isolation & numbering state |
| Auth | Laravel Breeze (Livewire) | Ringan, cocok tanpa admin panel berat |
| Document Processing | `phpoffice/phpword` | Presisi `.docx`, TemplateProcessor + clone row repeater |
| Styling | Tailwind v4 + shadcn/ui | Konsistensi UI cepat |
| Hosting | Dokploy + Dockerfile | Deployment ringan self-hosted |

## 7. Success Metrics & KPIs

| KPI | Target |
|:---|:---|
| Waktu buat surat massal 20 penerima | Turun ≥ 80% vs manual |
| Error penomoran bentrok | 0 per workspace |
| Surat dengan layout konsisten | 100% hasil generate |
| Adopsi bulk generation | ≥ 60% surat massal via fitur |

## 8. Risk Analysis & Mitigation

| Risk | Impact | Mitigation |
|:---|:---|:---|
| Parsing `${...}` gagal pada template kompleks | Form tidak muncul | Validasi template saat upload; preview variabel terdeteksi |
| Race condition penomoran konkuren | Nomor bentrok | DB transaction + unique constraint per workspace |
| Bulk generate timeout | File gagal | Queue job + notifikasi selesai |
| Revisi tanpa jejak audit | Sengketa riwayat | Wajib alasan revisi (FR-07); status `Hangus` permanen |

## 9. Constraints & Assumptions

- TALL stack murni, tanpa admin panel berat.
- Tanda tangan elektronik/QR **tidak** termasuk (DEC-004 = fisik).
- Format nomor kustom disimpan sebagai pola string; token yang didukung terbatas pada nomor urut, kode, bulan, tahun.
- Nilai deadline, biaya, dan SLA retensi file: **TBD**.

## 10. Out of Scope

- Tanda tangan elektronik & validasi QR (Future v2.0).
- Integrasi WhatsApp Gateway.
- Kalender persuratan (FullCalendar).
- Manajemen arsip surat masuk & disposisi.
- Multi-tenant billing/SaaS subscription.