<?php

namespace App\Livewire;

use App\Models\BulkJob;
use App\Models\EnvelopeTemplate;
use App\Models\Letter;
use App\Models\SuratMasuk;
use App\Support\CurrentWorkspace;
use Livewire\Attributes\On;
use Livewire\Component;

class GlobalSearch extends Component
{
    public string $search = '';

    public bool $isOpen = false;

    public bool $isPreviewOpen = false;

    public ?string $previewType = null;

    public ?string $previewTitle = null;

    public ?string $previewUrl = null;

    public ?string $previewDownloadUrl = null;

    public ?string $previewExternalUrl = null;

    #[On('open-global-search')]
    public function openSpotlight(): void
    {
        $this->isOpen = true;
        $this->isPreviewOpen = false;
    }

    #[On('close-global-search')]
    public function closeSpotlight(): void
    {
        $this->isOpen = false;
    }

    public function openPreview(string $type, string|int $id): void
    {
        $user = auth()->user();
        $ws = app(CurrentWorkspace::class)->get() ?? $user?->activeWorkspace();

        if (! $user || ! $ws) {
            return;
        }

        $this->isOpen = false; // Close spotlight palette (anti-tumpuk)

        if ($type === 'letter_pdf') {
            $letter = Letter::where('workspace_id', $ws->id)->find($id);
            if ($letter) {
                $num = $letter->formatted_number ?? 'Draf';
                $this->previewType = 'letter_pdf';
                $this->previewTitle = 'Surat Keluar: ' . $num . ' — ' . ($letter->perihal ?: 'Tanpa Perihal');
                $this->previewUrl = route('letters.preview-pdf', $letter->id) . '?v=' . time();
                $this->previewDownloadUrl = route('letters.download', $letter->id);
                $this->previewExternalUrl = route('letters.preview-pdf', $letter->id);
                $this->isPreviewOpen = true;
            }
        } elseif ($type === 'envelope_pdf') {
            $letter = Letter::where('workspace_id', $ws->id)->find($id);
            if ($letter) {
                $num = $letter->formatted_number ?? 'Draf';
                $this->previewType = 'envelope_pdf';
                $this->previewTitle = 'Kop Amplop: ' . $num . ' — ' . ($letter->perihal ?: 'Tanpa Perihal');
                $this->previewUrl = route('letters.envelope.preview', $letter->id) . '?v=' . time();
                $this->previewDownloadUrl = route('letters.envelope.download', $letter->id);
                $this->previewExternalUrl = route('letters.envelope.preview', $letter->id);
                $this->isPreviewOpen = true;
            }
        } elseif ($type === 'surat_masuk') {
            $sm = SuratMasuk::where('workspace_id', $ws->id)->find($id);
            if ($sm) {
                $this->previewType = 'surat_masuk';
                $this->previewTitle = 'Berkas Surat Masuk: ' . $sm->nomor_agenda . ' — ' . ($sm->perihal ?: 'Tanpa Perihal');
                $this->previewUrl = route('surat-masuk.preview', $sm->id) . '?v=' . time();
                $this->previewDownloadUrl = route('surat-masuk.download', $sm->id);
                $this->previewExternalUrl = route('surat-masuk.preview', $sm->id);
                $this->isPreviewOpen = true;
            }
        } elseif ($type === 'bulk_pdf') {
            $job = BulkJob::where('workspace_id', $ws->id)->find($id);
            if ($job) {
                $this->previewType = 'bulk_pdf';
                $this->previewTitle = 'Batch 1 PDF Utuh: #' . substr($job->id, 0, 8) . ' — ' . ($job->perihal ?: 'Mailing');
                $this->previewUrl = route('bulk.export.pdf', $job->id) . '?v=' . time();
                $this->previewDownloadUrl = route('bulk.export.pdf', $job->id) . '?download=1';
                $this->previewExternalUrl = route('bulk.export.pdf', $job->id);
                $this->isPreviewOpen = true;
            }
        } elseif ($type === 'bulk_envelopes') {
            $job = BulkJob::where('workspace_id', $ws->id)->find($id);
            if ($job) {
                $this->previewType = 'bulk_envelopes';
                $this->previewTitle = 'Semua Amplop Batch: #' . substr($job->id, 0, 8) . ' — ' . ($job->perihal ?: 'Mailing');
                $this->previewUrl = route('bulk.envelopes.download', $job->id) . '?preview=1&v=' . time();
                $this->previewDownloadUrl = route('bulk.envelopes.download', $job->id);
                $this->previewExternalUrl = route('bulk.envelopes.download', $job->id) . '?preview=1';
                $this->isPreviewOpen = true;
            }
        }
    }

    public function closePreview(): void
    {
        $this->isPreviewOpen = false;
        $this->previewType = null;
        $this->previewTitle = null;
        $this->previewUrl = null;
        $this->previewDownloadUrl = null;
        $this->previewExternalUrl = null;
    }

    public function render()
    {
        $user = auth()->user();
        $ws = app(CurrentWorkspace::class)->get() ?? $user?->activeWorkspace();

        if (! $user || ! $ws || (! $this->isOpen && ! $this->isPreviewOpen)) {
            return view('livewire.global-search', [
                'letters' => collect(),
                'suratMasuks' => collect(),
                'envelopeTemplates' => collect(),
                'bulkJobs' => collect(),
                'quickActions' => collect(),
                'canAmplopPreview' => false,
                'canBulk' => false,
            ]);
        }

        $role = $ws->roleOf($user);
        $canSuratKeluar = $user->can('surat_keluar.view') || $user->can('surat_keluar.preview_download') || $user->can('surat_keluar.create') || ($role?->canWrite() ?? false);
        $canSuratMasuk = $user->can('surat_masuk.view') || $user->can('surat_masuk.preview_download') || ($role !== null);
        $canAmplopPreview = $user->can('amplop.preview_download') || $user->can('surat_keluar.create') || ($role?->canWrite() ?? false);
        $canBulk = $user->can('bulk.preview_download') || $user->can('surat_keluar.create') || ($role?->canWrite() ?? false);
        $canCreateLetter = $user->can('surat_keluar.create') || ($role?->canWrite() ?? false);
        $canCreateSuratMasuk = $user->can('surat_masuk.create') || ($role?->canWrite() ?? false);
        $canManageTemplates = (bool) $user->is_super_admin || $user->can('template.view') || $user->can('template.manage') || ($role?->isAdmin() ?? false);
        $canManageEnvelopeTemplates = (bool) $user->is_super_admin || $user->can('template.manage') || ($role?->isAdmin() ?? false);
        $isAdmin = (bool) $user->is_super_admin || ($role?->isAdmin() ?? false);
        $canManageNumbering = $user->can('penomoran.manage') || ($role?->isAdmin() || $role?->value === 'secretary');
        $canViewMembers = (bool) $user->is_super_admin || $user->can('member.view') || $user->can('role.view') || ($role?->isAdmin() ?? false);

        $allNavItems = [
            [
                'label' => 'Dashboard',
                'description' => 'Ringkasan & status persuratan organisasi',
                'url' => route('dashboard'),
                'icon' => 'layout-dashboard',
                'keywords' => 'dashboard beranda overview statistik ringkasan',
                'show' => true,
            ],
            [
                'label' => 'Daftar Surat Keluar',
                'description' => 'Lihat seluruh arsip & draf surat keluar',
                'url' => route('letters.index'),
                'icon' => 'files',
                'keywords' => 'surat keluar daftar surat draf arsip letter',
                'show' => $canSuratKeluar,
            ],
            [
                'label' => 'Buat Surat Keluar',
                'description' => 'Tulis surat dinas/kegiatan baru dengan template',
                'url' => route('letters.create'),
                'icon' => 'file-plus-2',
                'keywords' => 'buat surat tulis surat surat baru compose create',
                'show' => $canCreateLetter,
            ],
            [
                'label' => 'Surat Masuk',
                'description' => 'Kelola buku agenda & disposisi surat masuk',
                'url' => route('surat-masuk.index'),
                'icon' => 'inbox',
                'keywords' => 'surat masuk agenda disposisi terima surat inbox',
                'show' => $canSuratMasuk,
            ],
            [
                'label' => 'Catat Surat Masuk Baru',
                'description' => 'Registrasi surat masuk baru ke buku agenda',
                'url' => route('surat-masuk.create'),
                'icon' => 'file-down',
                'keywords' => 'catat surat masuk input registrasi terima add',
                'show' => $canCreateSuratMasuk,
            ],
            [
                'label' => 'Kalender Persuratan',
                'description' => 'Jadwal & linimasa kegiatan berdasarkan surat',
                'url' => route('calendar.index'),
                'icon' => 'calendar',
                'keywords' => 'kalender jadwal agenda kegiatan linimasa waktu calendar',
                'show' => true,
            ],
            [
                'label' => 'Bulk Generate (Mailing Massal)',
                'description' => 'Buat surat massal penerima berbeda sekaligus',
                'url' => route('bulk.index'),
                'icon' => 'layers',
                'keywords' => 'bulk generate mailing massal banyak penerima sertifikat batch',
                'show' => $canBulk,
            ],
            [
                'label' => 'Library Template Word (.docx)',
                'description' => 'Kelola master template surat organisasi',
                'url' => route('templates.index'),
                'icon' => 'file-code-2',
                'keywords' => 'template library word master docx format',
                'show' => $canManageTemplates,
            ],
            [
                'label' => 'Pengaturan Template Amplop',
                'description' => 'Kelola ukuran DL, C5, A4 & kop amplop',
                'url' => route('settings.envelopes'),
                'icon' => 'mail',
                'keywords' => 'template amplop kop amplop dl c5 a4 cetak amplop envelope',
                'show' => $canManageEnvelopeTemplates,
            ],
            [
                'label' => 'Builder Kop Surat',
                'description' => 'Desain visual kop surat & tata letak logo',
                'url' => route('settings.kop'),
                'icon' => 'stamp',
                'keywords' => 'kop surat builder header logo instansi surat',
                'show' => $isAdmin,
            ],
            [
                'label' => 'Pola Penomoran Otomatis',
                'description' => 'Konfigurasi format nomor surat & reset tahunan',
                'url' => route('settings.numbering'),
                'icon' => 'hash',
                'keywords' => 'penomoran pola format nomor urut sequence numbering',
                'show' => $canManageNumbering,
            ],
            [
                'label' => 'Jenis Surat & Kode Klasifikasi',
                'description' => 'Kelola parameter kategori surat (UND, SK, PENG, dll)',
                'url' => route('settings.letter-types'),
                'icon' => 'tag',
                'keywords' => 'jenis surat klasifikasi kategori parameter kode types',
                'show' => $canManageNumbering,
            ],
            [
                'label' => 'Anggota & Hak Akses',
                'description' => 'Undang pengurus & atur peran Spatie tim',
                'url' => route('settings.members'),
                'icon' => 'users',
                'keywords' => 'anggota member akses role izin undang invitation team',
                'show' => $canViewMembers,
            ],
        ];

        $term = trim($this->search);

        if (strlen($term) < 2) {
            $quickActions = collect($allNavItems)->filter(fn ($item) => $item['show'])->values();

            return view('livewire.global-search', [
                'letters' => collect(),
                'suratMasuks' => collect(),
                'envelopeTemplates' => collect(),
                'bulkJobs' => collect(),
                'quickActions' => $quickActions,
                'canAmplopPreview' => $canAmplopPreview,
                'canBulk' => $canBulk,
            ]);
        }

        // Quick Jump / Navigasi yang cocok dengan keyword pencarian
        $quickActions = collect($allNavItems)->filter(function ($item) use ($term) {
            if (! $item['show']) {
                return false;
            }
            $t = strtolower($term);

            return str_contains(strtolower($item['label']), $t)
                || str_contains(strtolower($item['keywords']), $t)
                || str_contains(strtolower($item['description']), $t);
        })->values();

        // 1. Surat Keluar (Top 5)
        $letters = collect();
        if ($canSuratKeluar) {
            $letters = Letter::where('workspace_id', $ws->id)
                ->where(function ($query) use ($term) {
                    $query->where('perihal', 'like', "%{$term}%")
                        ->orWhereHas('numbers', fn ($nq) => $nq->where('formatted_number', 'like', "%{$term}%"))
                        ->orWhere('field_values', 'like', "%{$term}%");
                })
                ->select(['id', 'workspace_id', 'template_id', 'perihal', 'status', 'letter_date', 'field_values', 'created_by', 'generated_file_path', 'preview_pdf_path'])
                ->with(['activeNumber', 'latestNumber', 'template:id,name'])
                ->orderByDesc('id')
                ->limit(5)
                ->get();
        }

        // 2. Surat Masuk (Top 5)
        $suratMasuks = collect();
        if ($canSuratMasuk) {
            $suratMasuks = SuratMasuk::where('workspace_id', $ws->id)
                ->where(function ($query) use ($term) {
                    $query->where('nomor_surat_pengirim', 'like', "%{$term}%")
                        ->orWhere('nomor_agenda', 'like', "%{$term}%")
                        ->orWhere('pengirim', 'like', "%{$term}%")
                        ->orWhere('perihal', 'like', "%{$term}%");
                })
                ->select(['id', 'workspace_id', 'nomor_agenda', 'nomor_surat_pengirim', 'pengirim', 'perihal', 'status', 'sifat_surat', 'tanggal_diterima', 'file_path', 'document_url'])
                ->orderByDesc('id')
                ->limit(5)
                ->get();
        }

        // 3. Template Amplop (Top 5)
        $envelopeTemplates = collect();
        if ($canManageEnvelopeTemplates) {
            $envelopeTemplates = EnvelopeTemplate::where('workspace_id', $ws->id)
                ->where('name', 'like', "%{$term}%")
                ->select(['id', 'workspace_id', 'name', 'preset_size', 'is_default', 'version'])
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->limit(5)
                ->get();
        }

        // 4. Bulk Jobs (Top 5)
        $bulkJobs = collect();
        if ($canBulk) {
            $bulkJobs = BulkJob::where('workspace_id', $ws->id)
                ->where(function ($query) use ($term) {
                    $query->where('id', 'like', "%{$term}%")
                        ->orWhere('perihal', 'like', "%{$term}%");
                })
                ->with(['template:id,name'])
                ->select(['id', 'workspace_id', 'template_id', 'perihal', 'status', 'recipient_count', 'processed_count', 'letter_date', 'created_at'])
                ->orderByDesc('created_at')
                ->limit(5)
                ->get();
        }

        return view('livewire.global-search', [
            'letters' => $letters,
            'suratMasuks' => $suratMasuks,
            'envelopeTemplates' => $envelopeTemplates,
            'bulkJobs' => $bulkJobs,
            'quickActions' => $quickActions,
            'canAmplopPreview' => $canAmplopPreview,
            'canBulk' => $canBulk,
        ]);
    }
}
