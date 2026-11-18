<?php

namespace App\Livewire\Admin;

use App\Actions\Templates\UploadTemplateAction;
use App\Enums\TemplateScope;
use App\Exceptions\DomainException;
use App\Models\LetterTemplate;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Master Template Global')]
class GlobalTemplatesIndex extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $description = '';

    public $file = null;

    public ?int $versionTargetId = null;

    public $versionFile = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        if (! auth()->user()?->is_super_admin) {
            abort(403, 'Hanya Super Admin yang dapat mengelola master template global.');
        }
    }

    public function uploadGlobal(UploadTemplateAction $action): void
    {
        $this->errorMessage = null;
        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'file' => ['required', 'file'],
        ]);

        try {
            $action->execute(
                $this->file,
                $this->name,
                auth()->user(),
                null,
                TemplateScope::Global,
                $this->description ?: null,
            );
        } catch (DomainException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->reset(['name', 'description', 'file']);
        $this->dispatch('toast', ['type' => 'success', 'title' => 'Master template global ditambahkan']);
    }

    public function uploadNewVersion(UploadTemplateAction $action): void
    {
        $this->errorMessage = null;
        $this->validate([
            'versionTargetId' => ['required', 'integer'],
            'versionFile' => ['required', 'file'],
        ]);

        $target = LetterTemplate::withoutGlobalScopes()
            ->where('scope', TemplateScope::Global->value)
            ->findOrFail($this->versionTargetId);

        try {
            $action->uploadNewVersion($target, $this->versionFile, auth()->user(), true);
        } catch (DomainException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->reset(['versionTargetId', 'versionFile']);
        $this->dispatch('toast', ['type' => 'success', 'title' => 'Versi baru template global diterbitkan']);
    }

    public function toggleDeprecate(int $templateId): void
    {
        $tpl = LetterTemplate::withoutGlobalScopes()
            ->where('scope', TemplateScope::Global->value)
            ->findOrFail($templateId);

        $tpl->forceFill(['is_deprecated' => ! $tpl->is_deprecated])->save();
    }

    public function render()
    {
        $templates = LetterTemplate::withoutGlobalScopes()
            ->where('scope', TemplateScope::Global->value)
            ->orderByDesc('id')
            ->get();

        return view('livewire.admin.global-templates-index', [
            'templates' => $templates,
        ]);
    }
}
