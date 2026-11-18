# API.md: SuratinAja

## 1. Authentication & Authorization

Autentikasi menggunakan **Laravel Breeze session-based cookie authentication** (bukan JWT). Seluruh endpoint berada di bawah middleware `web` + `auth` + `verified`. Setiap request wajib menyertakan header `X-CSRF-TOKEN` (diambil dari meta tag `<meta name="csrf-token">`) dan cookie sesi `laravel_session`. Untuk request Livewire, digunakan header `X-Livewire` bawaan framework.

Otorisasi per-workspace menggunakan **Laravel Policy** (`WorkspacePolicy`, `LetterPolicy`) yang memvalidasi keanggotaan user pada workspace (`workspace_members.role`) sebelum eksekusi action. Role yang diakui: `admin`, `secretary`, `chairman` (lihat PRD §3). Endpoint dengan aksi tulis (`POST`, `PATCH`, `DELETE`) menolak role `chairman` dengan `403`.

Format header standar:

```
Cookie: laravel_session=<session_id>
X-CSRF-TOKEN: <token>
X-Requested-With: XMLHttpRequest
Accept: application/json
Content-Type: application/json
```

## 2. Standard Response & Pagination Formats

**Sukses:** `{ "success": true, "data": {...}, "message": "..." }` dengan HTTP `2xx`.
**Error:** `{ "success": false, "error": { "code": "<ERROR_CODE>", "message": "...", "details": {...} } }` dengan HTTP `4xx`/`5xx`.
**Pagination:** `{ "data": [...], "meta": { "current_page": 1, "per_page": 15, "total": 42, "last_page": 3 } }` mengikuti format `LengthAwarePaginator` Laravel.

Kode error domain: `WS_FORBIDDEN`, `TEMPLATE_INVALID`, `NUMBER_CONFLICT`, `BULK_JOB_FAILED`, `REVISION_REASON_REQUIRED`.

## 3. API Endpoints

### 3.1 POST /api/v1/workspaces/{workspace}/letters

Membuat draft surat tunggal: memilih template (DEC-001), mem-parse variabel `${...}`, dan meng-generate nomor sesuai pola workspace (DEC-002).

- **Auth:** `admin`, `secretary` (member workspace)
- **Request Body:**

```json
{
  "template_id": 12,
  "template_source": "workspace",
  "perihal": "Undangan Rapat Koordinasi",
  "letter_date": "2025-06-15",
  "variables": {
    "nama_kegiatan": "Rapat Koordinasi HIMATRIS",
    "tanggal_kegiatan": "2025-06-20",
    "lampiran": [
      { "nama_barang": "Proyektor", "jumlah": 2 },
      { "nama_barang": "Sound System", "jumlah": 1 }
    ]
  }
}
```

- **Response Body (201):**

```json
{
  "success": true,
  "data": {
    "id": 301,
    "letter_number": "012/HIMATRIS/VI/2025",
    "status": "draft",
    "perihal": "Undangan Rapat Koordinasi",
    "template_id": 12,
    "workspace_id": 4,
    "created_at": "2025-06-15T08:12:44Z"
  },
  "message": "Draft surat berhasil dibuat."
}
```

- **Status Codes:** `201` sukses · `403` (`WS_FORBIDDEN`) · `409` (`NUMBER_CONFLICT` — race condition penomoran, retry) · `422` (`TEMPLATE_INVALID` — variabel wajib tidak terpenuhi)

### 3.2 PATCH /api/v1/workspaces/{workspace}/letters/{letter}/status

Mengubah status surat sesuai alur DEC-004: `draft` → `menunggu_ttd` → `selesai`. Transisi ke `selesai` hanya setelah tanda tangan basah.

- **Auth:** `admin`, `secretary`
- **Request Body:**

```json
{ "status": "menunggu_ttd" }
```

- **Response Body (200):**

```json
{
  "success": true,
  "data": { "id": 301, "status": "menunggu_ttd", "updated_at": "2025-06-15T09:00:00Z" },
  "message": "Status surat diperbarui."
}
```

- **Status Codes:** `200` · `403` (`WS_FORBIDDEN`) · `422` (transisi status ilegal, misal `draft` → `selesai`)

### 3.3 POST /api/v1/workspaces/{workspace}/letters/{letter}/revise

Revisi surat bernomor (DEC-005). Sekretaris memilih `use_new_number` atau `keep_number`, wajib menyertakan `revision_reason`. Bila `use_new_number=true`, nomor lama berstatus `hangus`.

- **Auth:** `admin`, `secretary`
- **Request Body:**

```json
{
  "use_new_number": true,
  "revision_reason": "Perubahan tanggal pelaksanaan kegiatan.",
  "variables": { "tanggal_kegiatan": "2025-06-25" }
}
```

- **Response Body (200):**

```json
{
  "success": true,
  "data": {
    "letter_id": 301,
    "old_number": "012/HIMATRIS/VI/2025",
    "old_number_status": "hangus",
    "new_number": "045/HIMATRIS/VI/2025",
    "revision_reason": "Perubahan tanggal pelaksanaan kegiatan."
  },
  "message": "Revisi surat tercatat."
}
```

- **Status Codes:** `200` · `403` · `422` (`REVISION_REASON_REQUIRED` bila `revision_reason` kosong)

### 3.4 POST /api/v1/workspaces/{workspace}/letters/bulk

Bulk generation (DEC-003, FR-05). Menerima array penerima (manual) atau referensi file upload (CSV/XLSX). Job di-queue; endpoint mengembalikan `job_id` untuk polling.

- **Auth:** `admin`, `secretary`
- **Request Body:**

```json
{
  "template_id": 12,
  "template_source": "workspace",
  "perihal": "Undangan Seminar OASIS",
  "letter_date": "2025-06-15",
  "shared_variables": { "nama_kegiatan": "Seminar OASIS 2025" },
  "recipients": {
    "mode": "manual",
    "items": [
      { "nama": "Dr. Budi Santoso, M.Kom.", "alamat": "Fakultas Teknik" },
      { "nama": "Ir. Siti Aminah, M.T.", "alamat": "Fakultas Sains" }
    ]
  }
}
```

Untuk `"mode": "upload"`, gunakan field `"file_upload_id": <id>` hasil endpoint upload file (out-of-scope dokumen ini, lihat `ARCHITECTURE.md`).

- **Response Body (202):**

```json
{
  "success": true,
  "data": {
    "job_id": "bulk_9f3a2c",
    "status": "queued",
    "recipient_count": 2,
    "poll_url": "/api/v1/jobs/bulk_9f3a2c"
  },
  "message": "Job bulk generation diterima."
}
```

- **Status Codes:** `202` diterima · `403` · `422` (`BULK_JOB_FAILED` untuk format penerima tidak valid)

### 3.5 GET /api/v1/workspaces/{workspace}/letters

Daftar surat per workspace dengan filter status dan pencarian nomor/perihal (FR-08). Read-only untuk semua role termasuk `chairman`.

- **Auth:** semua member workspace (`admin`, `secretary`, `chairman`)
- **Query Params:** `?status=selesai&search=undangan&page=1&per_page=15`
- **Response Body (200):**

```json
{
  "success": true,
  "data": [
    {
      "id": 301,
      "letter_number": "012/HIMATRIS/VI/2025",
      "perihal": "Undangan Rapat Koordinasi",
      "status": "selesai",
      "created_at": "2025-06-15T08:12:44Z"
    }
  ],
  "meta": { "current_page": 1, "per_page": 15, "total": 42, "last_page": 3 }
}
```

- **Status Codes:** `200` · `403` (`WS_FORBIDDEN` — user bukan member workspace)

> Catatan: endpoint builder kop surat, pola penomoran, dan manajemen anggota (FR-03, FR-04, FR-09, FR-11) diimplementasikan sebagai Livewire component action, bukan REST endpoint, sesuai arsitektur TALL stack murni. Lihat `ARCHITECTURE.md` untuk detail mekanisme internal.