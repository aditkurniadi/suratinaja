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

    public string $templateName = '';

    public string $templateDescription = '';

    public $templateFile = null;

    /** @var array<int, array<string, mixed>> */
    public array $detectedVariables = [];

    public ?string $uploadError = null;

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
            'templateFile' => ['required', 'file'],
        ]);

        try {
            $action->execute(
                $this->templateFile,
                $this->templateName,
                auth()->user(),
                $ws,
                TemplateScope::Workspace,
                $this->templateDescription ?: null,
            );
        } catch (DomainException $e) {
            $this->uploadError = $e->getMessage();

            return;
        }

        $this->reset(['showUploadModal', 'templateName', 'templateDescription', 'templateFile', 'detectedVariables', 'uploadError']);
        $this->activeTab = 'workspace';
        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Template berhasil diunggah',
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
