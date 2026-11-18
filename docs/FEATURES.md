# FEATURES.md: SuratinAja

## 1. Ringkasan Modul Fitur

SuratinAja dipecah menjadi enam modul fungsional yang masing-masing memiliki tanggung jawab tunggal. Setiap modul dijelaskan melalui user story, spesifikasi perilaku, acceptance criteria, dan edge case.

| Kode | Modul | Aktor Utama | Referensi |
|:---|:---|:---|:---|
| MOD-01 | Manajemen Workspace & Anggota | Admin Workspace | DEC-001, FR-09 |
| MOD-02 | Builder Kop Surat | Admin Workspace | FR-03 |
| MOD-03 | Template `.docx` & Form Dinamis | Admin, Sekretaris | DEC-001, FR-01, FR-02 |
| MOD-04 | Generator Nomor & State Surat | Sekretaris | DEC-002, DEC-004, FR-04, FR-06 |
| MOD-05 | Bulk Generation (Mailing) | Sekretaris | DEC-003, FR-05 |
| MOD-06 | Revisi & Riwayat Nomor | Sekretaris | DEC-005, FR-07 |

Detail skema database, endpoint API, dan arsitektur deployment tidak dibahas di dokumen ini — lihat `ARCHITECTURE.md` dan `API.md`.

## 2. MOD-01 — Manajemen Workspace & Anggota

### 2.1 User Story

Sebagai Admin Workspace, saya ingin mengundang anggota dan menetapkan peran (Sekretaris/Ketua) agar isolasi data dan hak akses antar organisasi terjaga.

### 2.2 Spesifikasi Perilaku

- Setiap user dapat tergabung pada lebih dari satu workspace dengan peran berbeda per workspace.
- Peran yang didukung: `admin`, `sekretaris`, `ketua`. Peran `ketua` bersifat read-only (lihat FR-03 pada PRD §3).
- Undangan anggota dibuat oleh Admin melalui input email; status undangan `pending` hingga user menerima. Mekanisme pengiriman undangan (email/tautan) ditetapkan sebagai **TBD**.
- Semua query data surat/template WAJIB di-scope ke `workspace_id` aktif (isolasi data, PRD §5).

### 2.3 Acceptance Criteria

| ID | Kriteria |
|:---|:---|
| AC-01-01 | User dengan peran `ketua` tidak dapat mengakses aksi create/update/delete pada modul surat. |
| AC-01-02 | User yang tidak tergabung dalam workspace menerima `403` saat mengakses resource workspace tersebut. |
| AC-01-03 | Admin dapat mencabut (revoke) anggota; anggota yang dicabut kehilangan akses seketika pada request berikutnya. |

### 2.4 Edge Case

- User diundang ke workspace yang sama dua kali → sistem menolak duplikasi (`unique(workspace_id, user_id)`).
- Admin terakhir mencoba keluar dari workspace → ditolak hingga ada admin lain (kebijakan minimum admin = **TBD**).

## 3. MOD-02 — Builder Kop Surat

### 3.1 User Story

Sebagai Admin Workspace, saya ingin mengonfigurasi kop surat (logo kiri, logo kanan, teks header, alamat) sekali saja agar otomatis menempel pada setiap surat yang digenerate.

### 3.2 Spesifikasi Perilaku

- Konfigurasi kop disimpan per workspace: `logo_kiri`, `logo_kanan`, `teks_header`, `alamat`.
- Kop dirender ke dalam `.docx` hasil generate melalui `phpoffice/phpword` (header section) sebelum variabel `${...}` diproses.
- Perubahan kop berlaku untuk generate berikutnya; surat yang sudah digenerate tidak berubah retroaktif.
- Format gambar yang didukung: PNG/JPG. Batas ukuran file logo = **TBD**.

### 3.3 Acceptance Criteria

| ID | Kriteria |
|:---|:---|
| AC-02-01 | Generate surat setelah kop dikonfigurasi menghasilkan file `.docx` dengan kop pada halaman pertama sesuai data workspace. |
| AC-02-02 | Workspace tanpa kop tetap dapat generate surat (kop kosong, tanpa error). |
| AC-02-03 | Hanya `admin` yang dapat mengubah konfigurasi kop. |

### 3.4 Edge Case

- Logo hilang/terhapus dari storage → generate gagal dengan pesan jelas, bukan file korup.
- Rasio logo ekstrem → layout header di-`contain` agar tidak merusak margin.

## 4. MOD-03 — Template `.docx` & Form Dinamis

### 4.1 User Story

Sebagai Sekretaris, saya ingin memilih template global atau mengunggah template mandiri, lalu mengisi form yang otomatis muncul dari variabel `${...}` agar tidak perlu menyentuh Word secara langsung.

### 4.2 Spesifikasi Perilaku (DEC-001, FR-01, FR-02)

- **Sumber template:** `global` (read-only, hanya dapat diduplikasi ke workspace) atau `workspace` (unggahan mandiri).
- **Parsing variabel:** Saat upload, sistem memindai seluruh `${...}` via `TemplateProcessor::getVariables()` dan menyimpannya sebagai skema variabel.
- **Tipe variabel yang didukung:**
  - `text` — input teks satu baris.
  - `date` — date picker (format tampilan = **TBD**).
  - `repeater` — untuk tabel lampiran dinamis (baris dapat ditambah/hapus), dirender via `cloneRow`.
- Tipe variabel ditentukan oleh konvensi penamaan atau metadata; aturan penentuan tipe eksplisit = **TBD** (lihat edge case).
- Template global tidak dapat diedit; aksi edit memaksa "Duplikasi ke Workspace" terlebih dahulu.

### 4.3 Acceptance Criteria

| ID | Kriteria |
|:---|:---|
| AC-03-01 | Upload template valid menampilkan daftar variabel terdeteksi sebelum disimpan. |
| AC-03-02 | Form Livewire merender satu field per variabel dengan tipe sesuai. |
| AC-03-03 | Template global tidak dapat diubah; tombol edit hanya memunculkan opsi duplikasi. |
| AC-03-04 | Generate dengan semua variabel terisi menghasilkan `.docx` tanpa placeholder `${...}` tersisa. |

### 4.4 Edge Case

- Template tanpa variabel → surat dapat digenerate sebagai dokumen statis (dengan kop).
- Variabel `repeater` kosong → tabel lampiran dirender tanpa baris (atau dihapus sesuai kebijakan = **TBD**).
- Upload file non-`.docx` → ditolak dengan validasi MIME.
- Parsing gagal pada template kompleks (mis. nested table) → upload ditolak dengan pesan; mitigasi PRD §8.

## 5. MOD-04 — Generator Nomor & State Surat

### 5.1 User Story

Sebagai Sekretaris, saya ingin sistem menghasilkan nomor surat otomatis sesuai pola workspace dan melacak status surat dari draft hingga selesai.

### 5.2 Spesifikasi Perilaku (DEC-002, DEC-004, FR-04, FR-06)

- **Pola nomor:** string template per workspace dengan token terbatas: `{nomor_urut}`, `{kode}`, `{bulan}`, `{tahun}`. Contoh pola: `{nomor_urut}/{kode}/{bulan}/{tahun}`.
- **Penomoran:** `nomor_urut` di-increment per workspace di dalam DB transaction dengan unique constraint untuk mencegah bentrok (PRD §8).
- **State machine surat:**
  - `Draft` → `Menunggu TTD` (aksi "Ajukan TTD" oleh Sekretaris).
  - `Menunggu TTD` → `Selesai` (aksi manual "Tandai Selesai" setelah tanda tangan basah, DEC-004).
  - `Hangus` — status terminal untuk nomor yang digantikan saat revisi (lihat MOD-06).
- Tidak ada tanda tangan elektronik/QR pada fase ini (out of scope PRD §10).

### 5.3 Acceptance Criteria

| ID | Kriteria |
|:---|:---|
| AC-04-01 | Dua permintaan generate nomor konkuren pada workspace yang sama tidak menghasilkan nomor duplikat. |
| AC-04-02 | Transisi status hanya mengikuti jalur yang diizinkan; transisi ilegal ditolak. |
| AC-04-03 | Surat berstatus `Selesai` tidak dapat kembali ke `Draft` tanpa proses revisi (MOD-06). |
| AC-04-04 | Format nomor yang dihasilkan sesuai pola workspace (token terisi benar). |

### 5.4 Edge Case

- Pola mengandung token tidak dikenal (mis. `{foo}`) → validasi pola menolak saat disimpan.
- Reset `nomor_urut` (mis. awal tahun) → kebijakan reset = **TBD**; sistem tidak me-reset otomatis.
- Generate nomor tanpa mengisi perihal → diizinkan pada `Draft`, validasi wajib = **TBD**.

## 6. MOD-05 — Bulk Generation (Mailing)

### 6.1 User Story

Sebagai Sekretaris, saya ingin membuat surat massal (mis. undangan 20+ dosen) melalui input manual atau upload file, dan mendapat satu file `.docx` utuh dengan nomor berurutan.

### 6.2 Spesifikasi Perilaku (DEC-003, FR-05)

- **Sumber penerima:** manual (array nama/gelar) dan upload file (CSV/XLSX). Keduanya dapat digabung.
- **Output:** satu file `.docx` berisi N halaman (satu halaman per penerima), masing-masing dengan nomor surat berurutan sesuai pola workspace.
- **Eksekusi:** dijalankan sebagai queue job untuk menghindari timeout (PRD §8); status job `queued`/`processing`/`done`/`failed`.
- **Notifikasi selesai:** mekanisme notifikasi (in-app/email) = **TBD**.
- Mapping kolom file upload ke variabel template = **TBD** (asumsi: header kolom = nama variabel).

### 6.3 Acceptance Criteria

| ID | Kriteria |
|:---|:---|
| AC-05-01 | Bulk 50 penerima selesai < 30 detik (NFR PRD §5) atau masuk antrean dengan status terpantau. |
| AC-05-02 | Nomor surat pada tiap halaman berurutan tanpa duplikat. |
| AC-05-03 | Baris file upload tidak valid dilaporkan per baris, bukan menggagalkan seluruh batch (kebijakan parsial = **TBD**). |
| AC-05-04 | Nama/gelar penerima tercetak persis sesuai input (tanpa human error copy-paste). |

### 6.4 Edge Case

- File upload kosong/format salah → ditolak dengan pesan validasi.
- Batch berukuran sangat besar → diproses chunked; batas maksimum penerima per batch = **TBD**.
- Kegagalan di tengah batch → job `failed`, nomor yang sudah terpakai tetap tercatat (tidak di-rollback otomatis) kecuali kebijakan = **TBD**.

## 7. MOD-06 — Revisi & Riwayat Nomor

### 7.1 User Story

Sebagai Sekretaris, saya ingin merevisi surat yang sudah bernomor dan memilih apakah memakai nomor baru atau tetap, dengan catatan alasan revisi, agar riwayat nomor tidak hilang.

### 7.2 Spesifikasi Perilaku (DEC-005, FR-07)

- Saat revisi surat bernomor, Sekretaris memilih salah satu:
  - **Nomor baru** → nomor lama berstatus `Hangus` (terminal, permanen) dan nomor baru di-generate.
  - **Tetap** → nomor lama dipertahankan; revisi dicatat pada riwayat.
- **Wajib mengisi alasan revisi** (PRD §8). Tanpa alasan, revisi ditolak.
- Setiap revisi menambah entri riwayat (versi, nomor, alasan, aktor, waktu).
- Status `Hangus` tidak dapat dikembalikan ke status aktif.

### 7.3 Acceptance Criteria

| ID | Kriteria |
|:---|:---|
| AC-06-01 | Revisi tanpa alasan ditolak dengan error validasi. |
| AC-06-02 | Memilih "nomor baru" mengubah nomor lama menjadi `Hangus` dan membuat nomor baru berurutan. |
| AC-06-03 | Memilih "tetap" mempertahankan nomor dan mencatat entri riwayat baru. |
| AC-06-04 | Nomor berstatus `Hangus` tidak pernah muncul sebagai nomor aktif dan tercatat permanen pada riwayat. |

### 7.4 Edge Case

- Revisi pada surat `Selesai` → diizinkan dengan alasan; perilaku status setelah revisi = **TBD**.
- Revisi berulang pada nomor yang sama → setiap iterasi tercatat sebagai versi baru.
- Konkurensi revisi pada surat yang sama → dikunci (lock) agar tidak ada dua nomor baru sekaligus.

## 8. Matriks Traceability Fitur

| Requirement | Modul | DEC | Dokumen Rujukan |
|:---|:---|:---|:---|
| FR-01 | MOD-03 | DEC-001 | `ARCHITECTURE.md` |
| FR-02 | MOD-03 | — | `ARCHITECTURE.md` |
| FR-03 | MOD-02 | — | `ARCHITECTURE.md` |
| FR-04 | MOD-04 | DEC-002 | `ARCHITECTURE.md` |
| FR-05 | MOD-05 | DEC-003 | `ARCHITECTURE.md` |
| FR-06 | MOD-04 | DEC-004 | `ARCHITECTURE.md` |
| FR-07 | MOD-06 | DEC-005 | `ARCHITECTURE.md` |
| FR-08 | MOD-04 | — | `API.md` |
| FR-09 | MOD-01 | — | `API.md` |
| FR-10 | MOD-02, MOD-04 | DEC-002 | `API.md` |
| FR-11 | MOD-03 | DEC-001 | `API.md` |

## 9. Nilai TBD yang Harus Ditetapkan Sebelum Implementasi

| Item | Modul | Status |
|:---|:---|:---|
| Mekanisme pengiriman undangan anggota | MOD-01 | TBD |
| Kebijakan minimum admin per workspace | MOD-01 | TBD |
| Batas ukuran file logo kop | MOD-02 | TBD |
| Format tampilan variabel `date` | MOD-03 | TBD |
| Aturan penentuan tipe variabel template | MOD-03 | TBD |
| Kebijakan tabel `repeater` kosong | MOD-03 | TBD |
| Kebijakan reset `nomor_urut` | MOD-04 | TBD |
| Validasi wajib perihal sebelum nomor | MOD-04 | TBD |
| Mekanisme notifikasi bulk selesai | MOD-05 | TBD |
| Mapping kolom upload ke variabel | MOD-05 | TBD |
| Batas maksimum penerima per batch | MOD-05 | TBD |
| Kebijakan partial-failure bulk | MOD-05 | TBD |
| Perilaku status setelah revisi surat `Selesai` | MOD-06 | TBD |