<?php

namespace App\Livewire\Templates;

use App\Actions\Templates\DuplicateTemplateAction;
use App\Actions\Templates\UploadTemplateAction;
use App\Enums\TemplateScope;
use App\Exceptions\DomainException;
use App\Models\LetterTemplate;
use App\Services\Templates\TemplateParser;
use App\Support\CurrentWorkspace;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Library Template Surat')]
class TemplatesIndex extends Component
{
    use WithFileUploads;

    public string $activeTab = 'workspace';

    public bool $showUploadModal = false;

    public bool $showGuideModal = false;

    public bool $showDeleteModal = false;

    public ?int $deletingTemplateId = null;

    public ?string $deletingTemplateName = null;

    public int $deletingLetterCount = 0;

    public int $deletingBulkCount = 0;

    public string $templateName = '';

    public string $templateDescription = '';

    public bool $hasEmbeddedKop = false;

    public $templateFile = null;

    /** @var array<int, array<string, mixed>> */
    public array $detectedVariables = [];

    public ?string $uploadError = null;

    public function updatingTemplateFile(): void
    {
        $this->detectedVariables = [];
        $this->uploadError = null;
        $this->templateName = '';
    }

    public function updatedTemplateFile(TemplateParser $parser): void
    {
        $this->detectedVariables = [];
        $this->uploadError = null;

        if (! $this->templateFile) {
            return;
        }

        try {
            $ext = strtolower($this->templateFile->getClientOriginalExtension());
            if ($ext !== 'docx') {
                throw DomainException::templateInvalid('Format file harus .docx.');
            }
            $this->detectedVariables = $parser->parse($this->templateFile->getRealPath());
            if ($this->templateName === '') {
                $this->templateName = pathinfo($this->templateFile->getClientOriginalName(), PATHINFO_FILENAME);
            }
        } catch (DomainException $e) {
            $this->uploadError = $e->getMessage();
        } catch (\Throwable $e) {
            $this->uploadError = 'Terjadi kendala saat memindai dokumen Word: '.$e->getMessage().'. Pastikan dokumen tidak terkunci dan berformat .docx valid.';
        }
    }

    public function saveTemplate(UploadTemplateAction $action): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        if (! $ws || ! $ws->roleOf(auth()->user())?->canWrite()) {
            abort(403);
        }

        $this->validate([
            'templateName' => ['required', 'string', 'max:150'],
            'templateDescription' => ['nullable', 'string', 'max:500'],
            'templateFile' => ['required', 'file', 'max:51200'],
            'hasEmbeddedKop' => ['boolean'],
        ]);

        try {
            $action->execute(
                $this->templateFile,
                $this->templateName,
                auth()->user(),
                $ws,
                TemplateScope::Workspace,
                $this->templateDescription ?: null,
                $this->hasEmbeddedKop,
            );
        } catch (DomainException $e) {
            $this->uploadError = $e->getMessage();

            return;
        }

        $this->reset(['showUploadModal', 'templateName', 'templateDescription', 'hasEmbeddedKop', 'templateFile', 'detectedVariables', 'uploadError']);
        $this->activeTab = 'workspace';
        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Template berhasil diunggah',
        ]);
    }

    public function toggleEmbeddedKop(int $templateId): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        $template = LetterTemplate::withoutGlobalScopes()->findOrFail($templateId);

        if ($template->isGlobal() || (int) $template->workspace_id !== (int) $ws?->id || ! $ws->roleOf(auth()->user())?->canWrite()) {
            abort(403, 'Template global bersifat read-only dan tidak dapat diubah.');
        }

        $template->forceFill(['has_embedded_kop' => ! $template->has_embedded_kop])->save();
        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Mode kop template diperbarui',
            'description' => $template->has_embedded_kop ? 'Menggunakan Kop Mandiri (Embedded)' : 'Menggunakan Kop Resmi Workspace',
        ]);
    }

    public function duplicateGlobal(int $templateId, DuplicateTemplateAction $action): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        if (! $ws || ! $ws->roleOf(auth()->user())?->canWrite()) {
            abort(403);
        }

        $source = LetterTemplate::withoutGlobalScopes()->findOrFail($templateId);
        $copy = $action->execute($source, $ws, auth()->user());

        $this->activeTab = 'workspace';
        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Template diduplikasi ke workspace',
            'description' => $copy->name,
        ]);
    }

    public function deprecateWorkspaceTemplate(int $templateId): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        $template = LetterTemplate::withoutGlobalScopes()->findOrFail($templateId);

        if ($template->isGlobal() || (int) $template->workspace_id !== (int) $ws?->id || ! $ws->roleOf(auth()->user())?->canWrite()) {
            abort(403, 'Template global bersifat read-only dan tidak dapat diubah.');
        }

        $template->forceFill(['is_deprecated' => ! $template->is_deprecated])->save();
        $this->dispatch('toast', [
            'type' => 'info',
            'title' => $template->is_deprecated ? 'Template diarsipkan' : 'Template diaktifkan kembali',
            'description' => $template->is_deprecated
                ? "Template '{$template->name}' disembunyikan dari pilihan pembuatan surat baru."
                : "Template '{$template->name}' dapat digunakan kembali.",
        ]);
    }

    public function confirmDelete(int $templateId): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        $template = LetterTemplate::withoutGlobalScopes()->findOrFail($templateId);

        if ($template->isGlobal() || (int) $template->workspace_id !== (int) $ws?->id || ! $ws->roleOf(auth()->user())?->canWrite()) {
            abort(403, 'Template global atau workspace lain tidak dapat dihapus.');
        }

        $this->deletingTemplateId = $template->id;
        $this->deletingTemplateName = $template->name;
        $this->deletingLetterCount = $template->letters()->count();
        $this->deletingBulkCount = $template->bulkJobs()->count();
        $this->showDeleteModal = true;
    }

    public function cancelDelete(): void
    {
        $this->reset(['showDeleteModal', 'deletingTemplateId', 'deletingTemplateName', 'deletingLetterCount', 'deletingBulkCount']);
    }

    public function deleteConfirmed(): void
    {
        if ($this->deletingTemplateId) {
            $id = $this->deletingTemplateId;
            $this->cancelDelete();
            $this->deleteWorkspaceTemplate($id);
        }
    }

    public function deleteWorkspaceTemplate(int $templateId): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        $template = LetterTemplate::withoutGlobalScopes()->findOrFail($templateId);

        if ($template->isGlobal() || (int) $template->workspace_id !== (int) $ws?->id || ! $ws->roleOf(auth()->user())?->canWrite()) {
            abort(403, 'Template global atau workspace lain tidak dapat dihapus.');
        }

        // Lepaskan relasi surat dan bulk jobs terkait agar dokumen historis tetap aman
        \App\Models\Letter::where('template_id', $template->id)->update(['template_id' => null]);
        \App\Models\BulkJob::where('template_id', $template->id)->update(['template_id' => null]);

        if ($template->file_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($template->file_path)) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($template->file_path);
        }

        $templateName = $template->name;
        $template->delete();

        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Template berhasil dihapus',
            'description' => "Template '{$templateName}' telah dihapus permanen.",
        ]);
    }

    public function render()
    {
        $ws = app(CurrentWorkspace::class)->get();
        $role = $ws ? $ws->roleOf(auth()->user()) : null;

        $workspaceTemplates = LetterTemplate::withoutGlobalScopes()
            ->where('scope', TemplateScope::Workspace->value)
            ->where('workspace_id', $ws?->id)
            ->orderByDesc('id')
            ->get();

        $globalTemplates = LetterTemplate::withoutGlobalScopes()
            ->where('scope', TemplateScope::Global->value)
            ->orderByDesc('id')
            ->get();

        return view('livewire.templates.templates-index', [
            'workspace' => $ws,
            'role' => $role,
            'workspaceTemplates' => $workspaceTemplates,
            'globalTemplates' => $globalTemplates,
        ]);
    }
}
