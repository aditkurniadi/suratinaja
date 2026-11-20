<?php

namespace App\Livewire\SuratMasuk;

use App\Enums\SifatSuratMasuk;
use App\Enums\StatusSuratMasuk;
use App\Models\SuratMasuk;
use App\Support\CurrentWorkspace;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Formulir Surat Masuk')]
class SuratMasukForm extends Component
{
    use WithFileUploads;

    public ?int $suratMasukId = null;

    public ?string $calendarContextNote = null;

    public string $nomorAgenda = '';
    public string $nomorSuratPengirim = '';
    public string $tanggalSurat = '';
    public string $tanggalDiterima = '';
    public string $pengirim = '';
    public string $perihal = '';
    public ?string $ringkasan = null;
    public string $sifatSurat = 'biasa';
    public ?string $tanggalAcaraDeadline = null;
    public string $status = 'baru';

    public $file = null;
    public ?string $existingFilePath = null;
    public ?string $existingFileName = null;
    public ?string $documentUrl = null;

    public ?int $suratKeluarId = null;
    public string $searchSuratKeluar = '';
    public bool $showSelectLetterModal = false;
    public ?string $nomorSuratBalasan = null;
    public ?string $catatanBalasan = null;

    public function mount(SuratMasuk|int|null $suratMasuk = null): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        if (! $ws) {
            abort(403);
        }

        if ($suratMasuk !== null) {
            $existing = $suratMasuk instanceof SuratMasuk ? $suratMasuk : SuratMasuk::findOrFail($suratMasuk);
            if ((int) $existing->workspace_id !== (int) $ws->id) {
                abort(403);
            }
            $canEdit = auth()->user()->can('surat_masuk.edit') || ($ws->roleOf(auth()->user())?->canWrite() ?? false);
            if (! $canEdit) {
                abort(403, 'Anda tidak memiliki hak akses mengubah surat masuk.');
            }

            $this->suratMasukId = $existing->id;
            $this->nomorAgenda = (string) $existing->nomor_agenda;
            $this->nomorSuratPengirim = (string) $existing->nomor_surat_pengirim;
            $this->tanggalSurat = $existing->tanggal_surat?->toDateString() ?? '';
            $this->tanggalDiterima = $existing->tanggal_diterima?->toDateString() ?? '';
            $this->pengirim = (string) $existing->pengirim;
            $this->perihal = (string) $existing->perihal;
            $this->ringkasan = $existing->ringkasan;
            $this->sifatSurat = $existing->sifat_surat?->value ?? 'biasa';
            $this->tanggalAcaraDeadline = $existing->tanggal_acara_atau_deadline?->toDateString();
            $this->status = $existing->status?->value ?? 'baru';
            $this->existingFilePath = $existing->file_path;
            $this->existingFileName = $existing->file_name;
            $this->documentUrl = $existing->document_url;
            $this->suratKeluarId = $existing->surat_keluar_id;
            $this->nomorSuratBalasan = $existing->nomor_surat_balasan;
            $this->catatanBalasan = $existing->catatan_balasan;

            return;
        }

        $canCreate = auth()->user()->can('surat_masuk.create') || ($ws->roleOf(auth()->user())?->canWrite() ?? false);
        if (! $canCreate) {
            abort(403, 'Anda tidak memiliki hak akses mencatat surat masuk.');
        }

        $this->tanggalDiterima = now()->toDateString();
        $this->tanggalSurat = now()->toDateString();
        $this->nomorAgenda = SuratMasuk::nextNomorAgenda($ws->id, now());

        $tanggalDiterimaParam = request()->query('tanggal_diterima');
        if ($tanggalDiterimaParam && strtotime($tanggalDiterimaParam)) {
            $this->tanggalDiterima = Carbon::parse($tanggalDiterimaParam)->toDateString();
            $this->tanggalSurat = $this->tanggalDiterima;
            $this->nomorAgenda = SuratMasuk::nextNomorAgenda($ws->id, Carbon::parse($tanggalDiterimaParam));
            $this->calendarContextNote = 'Tanggal diterima diisi otomatis dari kalender: ' . Carbon::parse($this->tanggalDiterima)->translatedFormat('d F Y');
        }

        $tanggalAcaraParam = request()->query('tanggal_acara');
        if ($tanggalAcaraParam && strtotime($tanggalAcaraParam)) {
            $this->tanggalAcaraDeadline = Carbon::parse($tanggalAcaraParam)->toDateString();
            $this->calendarContextNote = 'Tanggal kegiatan/deadline diisi otomatis dari kalender: ' . Carbon::parse($this->tanggalAcaraDeadline)->translatedFormat('d F Y');
        }
    }

    public function save()
    {
        $ws = app(CurrentWorkspace::class)->get();
        if ($this->suratMasukId) {
            $canEdit = auth()->user()->can('surat_masuk.edit') || ($ws->roleOf(auth()->user())?->canWrite() ?? false);
            if (! $canEdit) {
                abort(403);
            }
        } else {
            $canCreate = auth()->user()->can('surat_masuk.create') || ($ws->roleOf(auth()->user())?->canWrite() ?? false);
            if (! $canCreate) {
                abort(403);
            }
        }

        $this->validate([
            'nomorAgenda' => ['required', 'string', 'max:60'],
            'nomorSuratPengirim' => ['required', 'string', 'max:150'],
            'tanggalSurat' => ['required', 'date'],
            'tanggalDiterima' => ['required', 'date'],
            'pengirim' => ['required', 'string', 'max:200'],
            'perihal' => ['required', 'string', 'max:255'],
            'ringkasan' => ['nullable', 'string', 'max:2000'],
            'sifatSurat' => ['required', 'in:biasa,penting,segera'],
            'tanggalAcaraDeadline' => ['nullable', 'date'],
            'status' => ['required', 'in:baru,menunggu_disposisi,didisposisi,selesai,diarsipkan'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,docx', 'max:20480'], // max 20MB
            'documentUrl' => ['nullable', 'url', 'max:1000'],
        ]);

        // Flexible document check: must provide at least one (either file upload or external document URL)
        $hasFile = $this->file !== null || ! empty($this->existingFilePath);
        $hasUrl = ! empty(trim((string) $this->documentUrl));

        if (! $hasFile && ! $hasUrl) {
            $this->addError('document_source', 'Wajib menyertakan minimal salah satu: Unggah Berkas Dokumen ATAU Tautan Google Drive / Link Dokumen.');

            return;
        }

        $filePath = $this->existingFilePath;
        $fileName = $this->existingFileName;
        $fileMime = null;
        $fileSize = null;

        if ($this->file) {
            // Delete old file if replacing
            if ($this->existingFilePath && Storage::disk('local')->exists($this->existingFilePath)) {
                Storage::disk('local')->delete($this->existingFilePath);
            }

            $origName = $this->file->getClientOriginalName();
            $ext = $this->file->getClientOriginalExtension();
            $savedName = 'sm-' . Str::slug($this->nomorAgenda) . '-' . Str::random(6) . '.' . $ext;
            $filePath = $this->file->storeAs("surat-masuk/{$ws->id}", $savedName, 'local');
            $fileName = $origName;
            $fileMime = $this->file->getMimeType();
            $fileSize = $this->file->getSize();
        }

        $cleanUrl = trim((string) $this->documentUrl);
        $data = [
            'workspace_id' => $ws->id,
            'nomor_agenda' => trim($this->nomorAgenda),
            'nomor_surat_pengirim' => trim($this->nomorSuratPengirim),
            'tanggal_surat' => $this->tanggalSurat,
            'tanggal_diterima' => $this->tanggalDiterima,
            'pengirim' => trim($this->pengirim),
            'perihal' => trim($this->perihal),
            'ringkasan' => trim((string) $this->ringkasan) !== '' ? trim((string) $this->ringkasan) : null,
            'sifat_surat' => $this->sifatSurat,
            'tanggal_acara_atau_deadline' => $this->tanggalAcaraDeadline ?: null,
            'status' => $this->status,
            'surat_keluar_id' => $this->suratKeluarId ?: null,
            'nomor_surat_balasan' => trim((string) $this->nomorSuratBalasan) !== '' ? trim((string) $this->nomorSuratBalasan) : null,
            'catatan_balasan' => trim((string) $this->catatanBalasan) !== '' ? trim((string) $this->catatanBalasan) : null,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'document_url' => $cleanUrl !== '' ? $cleanUrl : null,
        ];

        if ($fileMime) {
            $data['file_mime'] = $fileMime;
        }
        if ($fileSize !== null) {
            $data['file_size'] = $fileSize;
        }

        if ($this->suratMasukId) {
            $sm = SuratMasuk::where('workspace_id', $ws->id)->findOrFail($this->suratMasukId);
            $sm->update($data);
            $message = "Surat masuk {$sm->nomor_agenda} berhasil diperbarui.";
        } else {
            $data['created_by'] = auth()->id();
            $sm = SuratMasuk::create($data);
            $message = "Surat masuk {$sm->nomor_agenda} berhasil dicatat.";
        }

        if ($this->suratKeluarId) {
            \App\Models\Letter::where('workspace_id', $ws->id)
                ->where('id', $this->suratKeluarId)
                ->update(['surat_masuk_id' => $sm->id]);
        }

        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Tersimpan',
            'description' => $message,
        ]);

        return redirect()->route('surat-masuk.show', $sm->id);
    }

    public function openSelectLetterModal(): void
    {
        $this->showSelectLetterModal = true;
    }

    public function closeSelectLetterModal(): void
    {
        $this->showSelectLetterModal = false;
    }

    public function selectSuratKeluar(int $id): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        $letter = \App\Models\Letter::where('workspace_id', $ws?->id)->find($id);
        if ($letter) {
            $this->suratKeluarId = $letter->id;
        }
        $this->showSelectLetterModal = false;
    }

    public function removeSuratKeluar(): void
    {
        $this->suratKeluarId = null;
    }

    public function render()
    {
        $ws = app(CurrentWorkspace::class)->get();
        $selectedLetter = $this->suratKeluarId
            ? \App\Models\Letter::with('activeNumber')->where('workspace_id', $ws?->id)->find($this->suratKeluarId)
            : null;

        $letterQuery = \App\Models\Letter::where('workspace_id', $ws?->id)
            ->with(['activeNumber', 'author'])
            ->orderByDesc('id');

        if (filled($this->searchSuratKeluar)) {
            $search = '%' . trim($this->searchSuratKeluar) . '%';
            $letterQuery->where(function ($q) use ($search) {
                $q->where('perihal', 'like', $search)
                    ->orWhereHas('activeNumber', fn ($sub) => $sub->where('formatted_number', 'like', $search));
            });
        }

        $availableLetters = $letterQuery->limit(20)->get();

        $pageTitle = $this->suratMasukId ? 'Ubah Surat Masuk' : 'Catat Surat Masuk';
        $backUrl = $this->suratMasukId ? route('surat-masuk.show', $this->suratMasukId) : route('surat-masuk.index');

        return view('livewire.surat-masuk.surat-masuk-form', [
            'sifatOptions' => SifatSuratMasuk::options(),
            'statusOptions' => StatusSuratMasuk::options(),
            'availableLetters' => $availableLetters,
            'selectedLetter' => $selectedLetter,
        ])->layout('components.layouts.app', [
            'title' => $pageTitle,
            'breadcrumbs' => [
                ['label' => 'Surat Masuk', 'url' => route('surat-masuk.index')],
                ['label' => $pageTitle],
            ],
            'backUrl' => $backUrl,
        ]);
    }
}
