<?php

namespace App\Livewire\Letters;

use App\Actions\Letters\CancelLetterAction;
use App\Actions\Letters\DeleteLetterAction;
use App\Actions\Letters\DuplicateLetterAction;
use App\Actions\Letters\ReviseLetterAction;
use App\Actions\Letters\TransitionLetterStatusAction;
use App\Actions\Letters\UploadManualDocumentAction;
use App\Enums\LetterStatus;
use App\Exceptions\DomainException;
use App\Models\Letter;
use App\Services\Documents\OnlyOfficeService;
use App\Services\Templates\VariableTypeResolver;
use App\Support\CurrentWorkspace;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Detail Surat')]
class LetterShow extends Component
{
    use WithFileUploads;

    public Letter|int $letter;

    #[Url(except: 'preview')]
    public string $activeRightTab = 'preview';

    public bool $showDetailModal = false;

    public bool $showReviseModal = false;

    public bool $useNewNumber = true;

    public string $revisionReason = '';

    public string $revisionPerihal = '';

    /** @var array<string, mixed> */
    public array $revisionVariables = [];

    public ?string $revisionError = null;

    public bool $showCancelModal = false;

    public string $cancelReason = '';

    public ?string $cancelError = null;

    public bool $showDeleteModal = false;

    public ?string $deleteError = null;

    public bool $showEnvelopeModal = false;

    public ?int $selectedEnvelopeTemplateId = null;

    public int $envelopePreviewVersion = 1;

    // Properti Edit Metadata & Upload Arsip Surat Manual
    public bool $showEditMetadataModal = false;

    public string $editPerihal = '';

    public string $editTanggalSurat = '';

    public string $editTanggalKegiatan = '';

    public string $editInstansiTujuan = '';

    public string $editKeterangan = '';

    public $newManualFile = null;

    public function mount(Letter|int $letter): void
    {
        $id = $letter instanceof Letter ? $letter->id : $letter;
        $this->letter = Letter::with(['activeNumber', 'numbers', 'revisions.actor', 'revisions.oldNumber', 'revisions.newNumber', 'activities.actor', 'template', 'author', 'suratMasuk'])
            ->findOrFail($id);

        $this->revisionPerihal = $this->letter->perihal;
        $this->revisionVariables = $this->letter->field_values ?? [];

        if ($this->letter->is_manual || $this->letter->is_manual_number || ! $this->letter->template_id) {
            $this->activeRightTab = 'preview';
        }
    }

    public function transition(string $target, TransitionLetterStatusAction $action): void
    {
        $canAcc = auth()->user()->can('surat_keluar.acc');
        $canEdit = auth()->user()->can('surat_keluar.edit');
        if (! $canAcc && ! $canEdit && ! (app(CurrentWorkspace::class)->get()?->roleOf(auth()->user())?->canWrite())) {
            $this->dispatch('toast', ['type' => 'error', 'title' => 'Akses ditolak', 'description' => 'Anda tidak memiliki hak akses mengubah status surat ini.']);
            return;
        }

        try {
            $this->letter = $action->execute($this->letter, auth()->user(), $target);
            $this->dispatch('toast', ['type' => 'success', 'title' => 'Status surat diperbarui']);
        } catch (DomainException $e) {
            $this->dispatch('toast', ['type' => 'error', 'title' => 'Transisi gagal', 'description' => $e->getMessage()]);
        }
    }

    public function submitRevision(ReviseLetterAction $action): void
    {
        if (! auth()->user()->can('surat_keluar.edit') && ! (app(CurrentWorkspace::class)->get()?->roleOf(auth()->user())?->canWrite())) {
            $this->revisionError = 'Anda tidak memiliki hak akses merevisi surat.';
            return;
        }

        $this->revisionError = null;

        try {
            $result = $action->execute($this->letter, auth()->user(), [
                'use_new_number' => $this->useNewNumber,
                'revision_reason' => $this->revisionReason,
                'perihal' => $this->revisionPerihal,
                'variables' => $this->revisionVariables,
            ]);

            $this->letter = $result['letter']->load(['activeNumber', 'numbers', 'revisions.actor', 'revisions.oldNumber', 'revisions.newNumber', 'activities.actor', 'template']);
            $this->showReviseModal = false;
            $this->revisionReason = '';

            $this->dispatch('toast', [
                'type' => 'success',
                'title' => 'Revisi surat tercatat',
                'description' => "Nomor aktif: {$result['new_number']}",
            ]);
        } catch (DomainException $e) {
            $this->revisionError = $e->getMessage();
        }
    }

    public function submitCancel(CancelLetterAction $action): void
    {
        if (! auth()->user()->can('surat_keluar.delete') && ! (app(CurrentWorkspace::class)->get()?->roleOf(auth()->user())?->canWrite())) {
            $this->cancelError = 'Anda tidak memiliki hak akses membatalkan surat.';
            return;
        }

        $this->cancelError = null;

        try {
            $this->letter = $action->execute($this->letter, auth()->user(), $this->cancelReason)
                ->load(['activeNumber', 'numbers', 'revisions.actor', 'activities.actor', 'template']);
            $this->showCancelModal = false;
            $this->cancelReason = '';

            $this->dispatch('toast', [
                'type' => 'success',
                'title' => 'Surat dibatalkan & nomor dihanguskan',
            ]);
        } catch (DomainException $e) {
            $this->cancelError = $e->getMessage();
        }
    }

    public function confirmDelete(DeleteLetterAction $action): void
    {
        $this->deleteError = null;

        try {
            $formattedNumber = $this->letter->formatted_number;
            $action->execute($this->letter, auth()->user());

            $this->dispatch('toast', [
                'type' => 'success',
                'title' => 'Draf surat berhasil dihapus',
                'description' => $formattedNumber ? "Nomor surat {$formattedNumber} dilepaskan dan tidak hangus." : 'Draf surat berhasil dihapus.',
            ]);

            $this->redirect(route('letters.index'), navigate: false);
        } catch (DomainException $e) {
            $this->deleteError = $e->getMessage();
        }
    }

    public function getCanDeleteProperty(): bool
    {
        $user = auth()->user();
        $workspace = app(CurrentWorkspace::class)->get();
        $role = $workspace ? $workspace->roleOf($user) : null;
        $hasPerm = $user?->is_super_admin || $user?->can('surat_keluar.delete') || ($role?->canWrite() ?? false);

        return $hasPerm && $this->letter->signed_at === null && $this->letter->status !== \App\Enums\LetterStatus::Selesai;
    }

    public function duplicate(DuplicateLetterAction $action): void
    {
        if (! auth()->user()->can('surat_keluar.create') && ! (app(CurrentWorkspace::class)->get()?->roleOf(auth()->user())?->canWrite())) {
            $this->dispatch('toast', ['type' => 'error', 'title' => 'Akses ditolak', 'description' => 'Anda tidak memiliki hak akses membuat/menduplikasi surat.']);
            return;
        }

        try {
            $copy = $action->execute($this->letter, auth()->user());
            $this->redirect(route('letters.show', $copy->id), navigate: false);
        } catch (DomainException $e) {
            $this->dispatch('toast', ['type' => 'error', 'title' => 'Gagal duplikasi', 'description' => $e->getMessage()]);
        }
    }

    public function refreshPreview(): void
    {
        $this->letter->refresh();
        $this->dispatch('toast', ['type' => 'success', 'title' => 'Pratinjau diperbarui']);
    }

    public function openEnvelopeModal(?int $templateId = null): void
    {
        $workspace = app(CurrentWorkspace::class)->get();
        if (! $workspace) {
            return;
        }

        if ($templateId !== null) {
            $this->selectedEnvelopeTemplateId = $templateId;
        } else {
            $defaultTemplate = \App\Models\EnvelopeTemplate::where('workspace_id', $workspace->id)
                ->where('is_default', true)
                ->first()
                ?? \App\Models\EnvelopeTemplate::where('workspace_id', $workspace->id)->first();

            $this->selectedEnvelopeTemplateId = $defaultTemplate?->id;
        }

        $this->envelopePreviewVersion = time();
        $this->showEnvelopeModal = true;
    }

    public function changeEnvelopeTemplate(int $templateId): void
    {
        $this->selectedEnvelopeTemplateId = $templateId;
        $this->envelopePreviewVersion = time();
    }

    public function openEditMetadataModal(): void
    {
        $this->editPerihal = $this->letter->perihal;
        $this->editTanggalSurat = $this->letter->letter_date?->toDateString() ?? '';
        $this->editTanggalKegiatan = $this->letter->tanggal_kegiatan?->toDateString() ?? '';
        $this->editInstansiTujuan = (string) ($this->letter->field_values['instansi'] ?? $this->letter->field_values['penerima'] ?? '');
        $this->editKeterangan = (string) ($this->letter->keterangan ?? '');
        $this->showEditMetadataModal = true;
    }

    public function saveMetadata(): void
    {
        $workspace = app(CurrentWorkspace::class)->get();
        $role = $workspace ? $workspace->roleOf(auth()->user()) : null;
        $canEdit = (auth()->user()?->can('surat_keluar.edit') || ($role?->canWrite() ?? false))
            && in_array($this->letter->status, [LetterStatus::Draft, LetterStatus::MenungguTtd], true);

        if (! $canEdit) {
            $this->dispatch('toast', ['type' => 'error', 'title' => 'Akses ditolak', 'description' => 'Anda tidak memiliki hak akses mengubah metadata surat ini.']);
            return;
        }

        $this->validate([
            'editPerihal' => ['required', 'string', 'max:255'],
            'editTanggalSurat' => ['required', 'date'],
            'editTanggalKegiatan' => ['nullable', 'date'],
            'editInstansiTujuan' => ['required', 'string', 'max:255'],
            'editKeterangan' => ['nullable', 'string', 'max:2000'],
        ], [
            'editPerihal.required' => 'Perihal surat wajib diisi.',
            'editInstansiTujuan.required' => 'Instansi tujuan surat wajib diisi.',
            'editTanggalSurat.required' => 'Tanggal surat wajib diisi.',
        ]);

        $fields = $this->letter->field_values ?? [];
        $fields['instansi'] = trim($this->editInstansiTujuan);
        if (empty($fields['penerima'])) {
            $fields['penerima'] = trim($this->editInstansiTujuan);
        }

        $this->letter->forceFill([
            'perihal' => trim($this->editPerihal),
            'letter_date' => $this->editTanggalSurat,
            'tanggal_kegiatan' => ! empty($this->editTanggalKegiatan) ? $this->editTanggalKegiatan : null,
            'keterangan' => trim($this->editKeterangan) ?: null,
            'field_values' => $fields,
        ])->save();

        $this->showEditMetadataModal = false;
        $this->letter->refresh();
        $this->dispatch('toast', ['type' => 'success', 'title' => 'Metadata surat berhasil diperbarui']);
    }

    public function uploadManualDocument(UploadManualDocumentAction $action): void
    {
        $workspace = app(CurrentWorkspace::class)->get();
        $role = $workspace ? $workspace->roleOf(auth()->user()) : null;
        $canEdit = (auth()->user()?->can('surat_keluar.edit') || ($role?->canWrite() ?? false))
            && in_array($this->letter->status, [LetterStatus::Draft, LetterStatus::MenungguTtd], true);

        if (! $canEdit) {
            $this->dispatch('toast', ['type' => 'error', 'title' => 'Akses ditolak', 'description' => 'Anda tidak memiliki hak akses mengunggah dokumen pada surat ini.']);
            return;
        }

        $this->validate([
            'newManualFile' => ['required', 'file', 'mimes:pdf,docx', 'max:20480'],
        ], [
            'newManualFile.required' => 'Pilih berkas PDF atau Word (.docx) terlebih dahulu.',
            'newManualFile.mimes' => 'Format berkas harus PDF (.pdf) atau Word (.docx).',
            'newManualFile.max' => 'Ukuran berkas maksimal 20 MB.',
        ]);

        try {
            $this->letter = $action->execute($this->letter, auth()->user(), $this->newManualFile);
            $this->newManualFile = null;
            $this->dispatch('toast', ['type' => 'success', 'title' => 'Berkas arsip surat berhasil diunggah']);
        } catch (\Throwable $e) {
            $this->dispatch('toast', ['type' => 'error', 'title' => 'Gagal mengunggah berkas', 'description' => $e->getMessage()]);
        }
    }

    public function render(VariableTypeResolver $resolver, OnlyOfficeService $onlyOfficeService)
    {
        $workspace = app(CurrentWorkspace::class)->get();
        $envelopeTemplates = $workspace
            ? \App\Models\EnvelopeTemplate::where('workspace_id', $workspace->id)->get()
            : collect();
        $role = $workspace ? $workspace->roleOf(auth()->user()) : null;
        $editableFields = $this->letter->template
            ? $resolver->editableFields($this->letter->template->variables_schema ?? [])
            : [];

        $user = auth()->user();
        $canEdit = ($user?->can('surat_keluar.edit') || ($role?->canWrite() ?? false))
            && in_array($this->letter->status, [\App\Enums\LetterStatus::Draft, \App\Enums\LetterStatus::MenungguTtd], true);

        $isManual = (bool) ($this->letter->is_manual || $this->letter->is_manual_number || ! $this->letter->template_id);

        $onlyOfficeConfig = null;
        if ($canEdit && ! $isManual && $this->activeRightTab === 'editor') {
            try {
                $onlyOfficeConfig = $onlyOfficeService->buildEditorConfig($this->letter, $user);
            } catch (\Throwable) {
                $onlyOfficeConfig = null;
            }
        }

        $letterLabel = 'Detail Surat' . ($this->letter->formatted_number ? " ({$this->letter->formatted_number})" : '');

        return view('livewire.letters.letter-show', [
            'workspace' => $workspace,
            'role' => $role,
            'canEdit' => $canEdit,
            'editableFields' => $editableFields,
            'onlyOfficeConfig' => $onlyOfficeConfig,
            'envelopeTemplates' => $envelopeTemplates,
        ])->layout('components.layouts.app', [
            'title' => $letterLabel,
            'breadcrumbs' => [
                ['label' => 'Daftar Surat', 'url' => route('letters.index')],
                ['label' => $letterLabel],
            ],
            'backUrl' => route('letters.index'),
            'fullWidth' => true,
        ]);
    }
}
