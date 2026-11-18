# TASKS.md: SuratinAja

## 1. Prinsip Backlog

- Setiap task adalah **slice end-to-end yang dapat diverifikasi** (data → API → UI → verifikasi), bukan task per-layer.
- Urutan mengikuti **dependency order**; task yang bergantung pada task lain wajib mencantumkan `Blocked by`.
- Task yang bergantung pada keputusan belum final ditandai **BLOCKED** dengan nama keputusan/TBD eksplisit (tidak diisi nilai karangan).
- Status implementasi **tidak diklaim selesai** di dokumen ini; hanya backlog.
- Referensi requirement: `FEATURES.md` (MOD-01..06, AC-xx-xx), `PRD.md` (FR-01..11), `API.md` (§3.1..3.5), `DATABASE.md` (§3.1..3.5), `DECISIONS.md` (DEC-001..005).

## 2. Ringkasan Fase

| Fase | Fokus | Task |
|:---|:---|:---|
| F0 | Fondasi: auth, workspace, isolasi data | TASK-01, TASK-02 |
| F1 | Konfigurasi workspace: kop & pola nomor | TASK-03, TASK-04 |
| F2 | Template `.docx` & form dinamis | TASK-05 |
| F3 | Surat tunggal: nomor + state machine | TASK-06, TASK-07 |
| F4 | Bulk generation | TASK-08 |
| F5 | Revisi & riwayat nomor | TASK-09 |
| F6 | Operasional & hardening | TASK-10 |

## 3. Daftar Task

### TASK-01 — Fondasi Auth & Multi-Workspace Membership

- **Tujuan:** User dapat login (Breeze+Livewire), membuat workspace, dan bergabung dengan peran `admin`/`secretary`/`chairman`; seluruh query ter-scope `workspace_id`.
- **Sumber:** MOD-01, AC-01-01..03, FR-09; `DATABASE.md` §3.1 (`workspaces`, `workspace_members`).
- **DEC:** —
- **Blocked by:** —
- **Langkah:** migrasi `workspaces` + `workspace_members` (`UNIQUE(workspace_id,user_id)`); `WorkspacePolicy`; global scope Eloquent per `workspace_id`; Livewire `WorkspaceSwitcher`; halaman kelola anggota (invite/revoke).
- **Acceptance:**
  - User non-member → `403` (`WS_FORBIDDEN`) pada resource workspace (AC-01-02).
  - Role `chairman` ditolak pada aksi tulis (AC-01-01).
  - Revoke anggota → request berikutnya `403` (AC-01-03).
  - Invite duplikat ditolak oleh constraint unik.
- **Verifikasi:** Feature test `WorkspaceIsolationTest` (member/non-member/chairman); manual: dua workspace paralel tidak saling melihat data.
- **Catatan BLOCKED:** Mekanisme pengiriman undangan (email vs tautan) = **TBD** (MOD-01 §2.2). Kebijakan minimum admin = **TBD** (MOD-01 §2.4). Slice inti (invite manual oleh admin) tetap dapat dikerjakan; hanya kanal pengiriman yang menunggu keputusan.

### TASK-02 — Builder Kop Surat per Workspace

- **Tujuan:** Admin mengonfigurasi `kop_logo_left`, `kop_logo_right`, `kop_header_text`, `kop_address`; konfigurasi tersimpan dan dapat dipratinjau.
- **Sumber:** MOD-02, FR-03, AC-02-01..03; `DATABASE.md` §3.1.
- **DEC:** —
- **Blocked by:** TASK-01
- **Langkah:** Livewire `KopBuilder` (upload PNG/JPG, validasi rasio `contain`); simpan path ke `workspaces`; preview header HTML; guard `admin`-only.
- **Acceptance:**
  - Hanya `admin` dapat mengubah kop (AC-02-03).
  - Workspace tanpa kop tetap valid (AC-02-02).
  - Logo hilang → error jelas, bukan file korup (MOD-02 §3.4).
- **Verifikasi:** Feature test `KopBuilderTest` (role guard, upload valid/invalid); manual: preview logo kiri/kanan sejajar.
- **Catatan BLOCKED:** Batas ukuran file logo = **TBD** (MOD-02 §3.2); gunakan validasi sementara yang dapat dikonfigurasi tanpa mengklaim nilai final.

### TASK-03 — Konfigurasi Pola Penomoran Workspace

- **Tujuan:** Admin menyimpan `number_pattern` (token `{seq}`, `{kode}`, `{bulan_romawi}`, `{tahun}`) dan sistem merender contoh nomor dari pola.
- **Sumber:** MOD-04, FR-04, DEC-002; `DATABASE.md` §3.5 (`letter_number_sequences`).
- **DEC:** DEC-002
- **Blocked by:** TASK-01
- **Langkah:** migrasi `letter_number_sequences` (`UNIQUE(workspace_id, period_key)`); service `NumberPatternRenderer` (parse token, validasi token tak dikenal); Livewire form pola + live preview; simpan `number_pattern` ke `workspaces`.
- **Acceptance:**
  - Pola `{seq}/{kode}/{bulan_romawi}/{tahun}` menghasilkan contoh `012/HIMATRIS/VI/2025`.
  - Token tidak dikenal ditolak dengan pesan validasi.
  - Dua request konkuren pada periode sama tidak menghasilkan `last_sequence` duplikat (constraint unik + transaksi).
- **Verifikasi:** Unit test `NumberPatternRendererTest` (semua token + token invalid); Feature test konkurensi dengan `DB::transaction` + retry.
- **Catatan BLOCKED:** Format tampilan `bulan_romawi` (pad nol `seq`?) = **TBD**; simpan sebagai opsi konfigurasi, jangan hardcode.

### TASK-04 — Library Template `.docx` & Parsing Variabel

- **Tujuan:** Admin/Sekretaris mengunggah `.docx` workspace atau menduplikasi template global; sistem mem-parse `${...}` menjadi `variables_schema` dengan tipe `text`/`date`/`repeater`.
- **Sumber:** MOD-03, FR-01, FR-02, DEC-001; `DATABASE.md` §3.4 (`letter_templates`).
- **DEC:** DEC-001
- **Blocked by:** TASK-01
- **Langkah:** migrasi `letter_templates` (`scope` GLOBAL/WORKSPACE); `TemplateParserService` via `TemplateProcessor::getVariables()`; normalisasi tipe (text/date/repeater); endpoint upload + duplikasi global→workspace; guard: template GLOBAL read-only.
- **Acceptance:**
  - Upload template valid → `variables_schema` terisi dan tampil di preview.
  - Edit template GLOBAL ditolak; aksi duplikasi berhasil membuat salinan WORKSPACE.
  - Template tanpa `${...}` → validasi menolak dengan kode `TEMPLATE_INVALID`.
- **Verifikasi:** Feature test `TemplateUploadTest` (valid/invalid/duplikat/global-edit-ditolak); manual: unggah contoh `.docx` dengan `${nama_kegiatan}` dan cek schema.
- **Catatan BLOCKED:** Aturan penentuan tipe variabel (konvensi penamaan vs metadata) = **TBD** (MOD-03 §4.2); implementasi awal pakai konvensi prefix, ditandai agar mudah diganti.

### TASK-05 — Buat Draft Surat Tunggal (Form Dinamis + Nomor)

- **Tujuan:** Sekretaris memilih template, mengisi form dinamis (termasuk repeater lampiran), lalu draft tersimpan dengan nomor ter-generate.
- **Sumber:** MOD-03, MOD-04, FR-02, FR-04, API `POST /api/v1/workspaces/{workspace}/letters` (§3.1).
- **DEC:** DEC-001, DEC-002
- **Blocked by:** TASK-02, TASK-03, TASK-04
- **Langkah:** Livewire `LetterComposer` (render field dari `variables_schema`, repeater Alpine); action `CreateLetterAction` (transaksi: ambil `last_sequence` → render nomor → insert `letters` + `letter_numbers` AKTIF); guard `admin`/`secretary`.
- **Acceptance:**
  - Response `201` dengan `letter_number` sesuai pola workspace.
  - Variabel wajib kosong → `422` `TEMPLATE_INVALID`.
  - Race condition → `409` `NUMBER_CONFLICT` dan retry berhasil.
  - `chairman` → `403`.
- **Verifikasi:** Feature test `CreateLetterTest` (happy path, invalid variable, race via dua request paralel); manual end-to-end lewat UI.

### TASK-06 — State Machine Status Surat (DEC-004)

- **Tujuan:** Transisi `draft` → `menunggu_ttd` → `selesai` hanya melalui aksi manual sekretaris; `signed_at` diisi saat `selesai`.
- **Sumber:** MOD-04, FR-06, API `PATCH .../letters/{letter}/status` (§3.2), AC state.
- **DEC:** DEC-004
- **Blocked by:** TASK-05
- **Langkah:** `LetterStatusTransition` (enum + guard transisi legal); endpoint PATCH; tombol aksi di daftar surat; isi `signed_at` saat transisi ke `selesai`.
- **Acceptance:**
  - `draft` → `menunggu_ttd` → `selesai` berhasil.
  - Transisi ilegal (`draft` → `selesai`) → `422`.
  - `chairman` → `403`.
- **Verifikasi:** Feature test `LetterStatusTest` (semua kombinasi transisi); manual: sekretaris menandai selesai setelah cetak & TTD basah.

### TASK-07 — Daftar Surat, Filter & Pencarian

- **Tujuan:** Semua member workspace (termasuk `chairman`) melihat daftar surat dengan filter status dan pencarian nomor/perihal.
- **Sumber:** MOD-04, FR-08, API `GET /api/v1/workspaces/{workspace}/letters` (§3.5).
- **DEC:** —
- **Blocked by:** TASK-05
- **Langkah:** Livewire `LetterIndex` (pagination 15, filter `status`, `search`); query scope `workspace_id`; kolom nomor/perihal/status/tanggal; badge status.
- **Acceptance:**
  - `chairman` dapat membaca daftar (read-only) tanpa aksi tulis.
  - Filter `status=selesai` dan `search=undangan` mengembalikan hasil benar.
  - Data workspace lain tidak pernah muncul.
- **Verifikasi:** Feature test `LetterIndexTest` (filter, search, isolasi); manual dengan data seed dua workspace.

### TASK-08 — Bulk Generation (Queue + CSV/XLSX)

- **Tujuan:** Sekretaris membuat surat massal via input manual atau upload CSV/XLSX; output satu `.docx` berisi banyak halaman dengan nomor berurutan; job di-queue.
- **Sumber:** MOD-05, FR-05, DEC-003, API `POST .../letters/bulk` (§3.4).
- **DEC:** DEC-003
- **Blocked by:** TASK-05
- **Langkah:** Livewire `BulkGenerator` (tab manual vs upload); `ImportRecipientsAction` (validasi kolom CSV/XLSX); `GenerateBulkLetterJob` (loop penerima → `cloneRow` repeater → gabung dokumen); endpoint `202` + `poll_url`; notifikasi selesai.
- **Acceptance:**
  - Manual 2 penerima → `202` dengan `recipient_count=2`.
  - Format file tidak valid → `422` `BULK_JOB_FAILED`.
  - Job selesai → file `.docx` berisi N halaman dengan nomor berurutan tanpa bentrok.
- **Verifikasi:** Feature test `BulkGenerationTest` (manual, upload valid/invalid, kuota nomor); manual: generate 20 penerima, cek urutan nomor & jumlah halaman.
- **Catatan BLOCKED:** Ukuran file template maks & retensi file hasil generate = **TBD** (PRD §5); batas jumlah penerima per job = **TBD**; jangan tetapkan angka final.

### TASK-09 — Revisi Surat & Riwayat Nomor (Hangus)

- **Tujuan:** Sekretaris merevisi surat bernomor dengan pilihan `use_new_number` atau `keep_number`, wajib `revision_reason`; nomor lama berstatus `HANGUS` bila diganti, dan riwayat dapat dilihat.
- **Sumber:** MOD-06, FR-07, DEC-005, API `POST .../letters/{letter}/revise` (§3.3); `DATABASE.md` §3.3 (`letter_numbers.revision_of`).
- **DEC:** DEC-005
- **Blocked by:** TASK-05, TASK-06
- **Langkah:** migrasi kolom `status`/`revision_reason`/`revision_of`; `ReviseLetterAction` (transaksi: validasi alasan → jika `use_new_number` set lama `HANGUS` + buat nomor baru AKTIF → update `field_values`); Livewire modal revisi; halaman riwayat nomor per surat.
- **Acceptance:**
  - `revision_reason` kosong → `422` `REVISION_REASON_REQUIRED`.
  - `use_new_number=true` → nomor lama `hangus`, nomor baru AKTIF, `revision_of` terisi.
  - `use_new_number=false` → nomor tetap, alasan tercatat.
  - Riwayat menampilkan rantai revisi.
- **Verifikasi:** Feature test `RevisionTest` (reason kosong, new number, keep number, riwayat); manual: revisi surat `selesai` dan cek status nomor lama.

### TASK-10 — Deployment & Operasional (Dokploy + Dockerfile)

- **Tujuan:** Aplikasi ter-build dan berjalan di Dokploy; queue worker aktif; backup harian DB terjadwal.
- **Sumber:** PRD §5 (backup harian), §6 (hosting); `ARCHITECTURE.md` (detail deployment).
- **DEC:** —
- **Blocked by:** TASK-01..TASK-09 (minimal TASK-05 & TASK-08 untuk queue)
- **Langkah:** Dockerfile multi-stage (PHP 8.3 + Node build Tailwind v4); konfigurasi `.env` produksi; service queue worker (`php artisan queue:work`); cron backup MySQL harian.
- **Acceptance:**
  - Container start bersih; health check lulus.
  - Job bulk diproses oleh queue worker.
  - Backup harian menghasilkan dump DB yang dapat direstore.
- **Verifikasi:** Smoke test deployment; restore backup di environment staging.
- **Catatan BLOCKED:** Retensi file `.docx` hasil generate = **TBD** (PRD §5); kebijakan retensi backup = **TBD**.

## 4. Matriks Traceability

| Requirement | Task |
|:---|:---|
| FR-01, DEC-001 | TASK-04 |
| FR-02 | TASK-04, TASK-05 |
| FR-03 | TASK-02 |
| FR-04, DEC-002 | TASK-03, TASK-05 |
| FR-05, DEC-003 | TASK-08 |
| FR-06, DEC-004 | TASK-06 |
| FR-07, DEC-005 | TASK-09 |
| FR-08 | TASK-07 |
| FR-09 | TASK-01 |
| FR-10 | TASK-02, TASK-03 |
| FR-11 | TASK-04 |

## 5. Daftar BLOCKED (Ringkas)

| Task | Keputusan/TBD |
|:---|:---|
| TASK-01 | Kanal undangan anggota; minimum admin per workspace |
| TASK-02 | Batas ukuran file logo |
| TASK-03 | Format tampilan `bulan_romawi`/pad `seq` |
| TASK-04 | Aturan penentuan tipe variabel |
| TASK-08 | Ukuran file maks; retensi file; batas penerima per job |
| TASK-10 | Retensi file hasil generate; retensi backup |

<!-- SDD_SOURCE_DOCS: api,database,decisions,design,features,prd -->
