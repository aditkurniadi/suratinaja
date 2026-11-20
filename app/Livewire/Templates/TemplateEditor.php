<?php

namespace App\Livewire\Templates;

use App\Actions\Templates\DuplicateTemplateAction;
use App\Models\LetterTemplate;
use App\Services\Documents\DocxRenderer;
use App\Services\Documents\OnlyOfficeService;
use App\Services\Templates\VariableTypeResolver;
use App\Support\CurrentWorkspace;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Editor Template Word')]
class TemplateEditor extends Component
{
    public LetterTemplate $template;

    public bool $canEdit = false;

    public string $initialDocKey = '';

    public bool $showSettingsModal = false;

    public string $editName = '';

    public string $editDescription = '';

    public bool $editHasEmbeddedKop = false;

    public function mount(LetterTemplate|int $template, OnlyOfficeService $onlyOfficeService): void
    {
        $id = $template instanceof LetterTemplate ? $template->id : $template;
        $tpl = LetterTemplate::withoutGlobalScopes()->findOrFail($id);
        $workspace = app(CurrentWorkspace::class)->get();
        $user = auth()->user();

        if (! $tpl->isGlobal()) {
            if (! $workspace || (int) $tpl->workspace_id !== (int) $workspace->id) {
                abort(403, 'Anda tidak memiliki akses ke template ini.');
            }
            $this->canEdit = $workspace->roleOf($user)?->canWrite() === true;
        } else {
            $this->canEdit = (bool) $user?->is_super_admin;
        }

        $this->template = $tpl;
        $this->initialDocKey = $onlyOfficeService->getTemplateDocumentKey($tpl);
        $this->editName = (string) $tpl->name;
        $this->editDescription = (string) ($tpl->description ?? '');
        $this->editHasEmbeddedKop = (bool) $tpl->has_embedded_kop;
    }

    public function syncVariables(OnlyOfficeService $onlyOfficeService): void
    {
        $res = $onlyOfficeService->forceSaveAndSyncTemplate(
            $this->template,
            1500,
            $this->initialDocKey !== '' ? $this->initialDocKey : null
        );
        $this->template->refresh();

        $count = count($res['variables']);

        if ($count > 0) {
            $this->dispatch('toast', [
                'type' => 'success',
                'title' => 'Variabel template disinkronkan',
                'description' => "{$count} variabel terdeteksi pada template (v{$this->template->version}).",
            ]);
        } else {
            $this->dispatch('toast', [
                'type' => 'warning',
                'title' => 'Belum ada variabel terdeteksi',
                'description' => 'Sisipkan minimal 1 variabel ${...} lalu tekan Ctrl+S sebelum menyinkronkan.',
            ]);
        }
    }

    public function openSettings(): void
    {
        if (! $this->canEdit) {
            abort(403);
        }

        $this->template->refresh();
        $this->editName = (string) $this->template->name;
        $this->editDescription = (string) ($this->template->description ?? '');
        $this->editHasEmbeddedKop = (bool) $this->template->has_embedded_kop;
        $this->showSettingsModal = true;
    }

    public function saveSettings(): void
    {
        if (! $this->canEdit) {
            abort(403);
        }

        $this->validate([
            'editName' => ['required', 'string', 'max:150'],
            'editDescription' => ['nullable', 'string', 'max:500'],
            'editHasEmbeddedKop' => ['boolean'],
        ]);

        $this->template->forceFill([
            'name' => trim($this->editName),
            'description' => trim($this->editDescription) !== '' ? trim($this->editDescription) : null,
            'has_embedded_kop' => $this->editHasEmbeddedKop,
        ])->save();

        $this->showSettingsModal = false;
        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Pengaturan template disimpan',
        ]);
    }

    public function alignKopMargin(OnlyOfficeService $onlyOfficeService, DocxRenderer $docxRenderer): void
    {
        if (! $this->canEdit) {
            abort(403);
        }

        $onlyOfficeService->forceSaveAndSyncTemplate(
            $this->template,
            1500,
            $this->initialDocKey !== '' ? $this->initialDocKey : null
        );
        $this->template->refresh();

        $absPath = Storage::disk('local')->path($this->template->file_path);
        if (! file_exists($absPath)) {
            $this->dispatch('toast', [
                'type' => 'error',
                'title' => 'File template tidak ditemukan',
            ]);

            return;
        }

        $changed = $docxRenderer->sanitizeHeaderAndMarginsInDocx($absPath, hasKop: true, force: true);
        $this->showSettingsModal = false;

        if (! $changed) {
            $this->dispatch('toast', [
                'type' => 'info',
                'title' => 'Margin kop sudah optimal',
                'description' => 'Tidak ada perubahan margin header yang diperlukan pada master template ini.',
            ]);

            return;
        }

        $this->template->forceFill([
            'version' => ((int) $this->template->version) + 1,
        ])->save();

        session()->flash('status', 'Margin kop surat berhasil dirapikan langsung pada master template (v'.$this->template->version.').');
        $this->redirect(route('templates.edit', $this->template->id), navigate: false);
    }

    public function duplicateToWorkspace(DuplicateTemplateAction $action): void
    {
        $workspace = app(CurrentWorkspace::class)->get();
        if (! $workspace || ! $workspace->roleOf(auth()->user())?->canWrite()) {
            abort(403);
        }

        $copy = $action->execute($this->template, $workspace, auth()->user());
        $this->redirect(route('templates.edit', $copy->id), navigate: false);
    }

    public function render(OnlyOfficeService $onlyOfficeService, VariableTypeResolver $resolver, DocxRenderer $docxRenderer)
    {
        $workspace = app(CurrentWorkspace::class)->get();
        $role = $workspace ? $workspace->roleOf(auth()->user()) : null;

        $onlyOfficeConfig = null;
        $configError = null;
        try {
            $onlyOfficeConfig = $onlyOfficeService->buildTemplateEditorConfig(
                $this->template,
                auth()->user(),
                $this->canEdit
            );
        } catch (\Throwable $e) {
            $configError = $e->getMessage();
        }

        $hasWordHeader = false;
        if ($this->template->file_path && Storage::disk('local')->exists($this->template->file_path)) {
            $hasWordHeader = $docxRenderer->hasWordHeader(Storage::disk('local')->path($this->template->file_path));
        }

        $backUrl = $this->template->isGlobal() && auth()->user()?->is_super_admin
            ? route('admin.global-templates')
            : route('templates.index');

        return view('livewire.templates.template-editor', [
            'workspace' => $workspace,
            'role' => $role,
            'onlyOfficeConfig' => $onlyOfficeConfig,
            'configError' => $configError,
            'backUrl' => $backUrl,
            'hasWordHeader' => $hasWordHeader,
            'systemCatalog' => $resolver->systemVariableCatalog(),
            'quickCustomVars' => $resolver->quickCustomVariables(),
            'repeaterPresets' => $resolver->repeaterTablePresets(),
        ])->layout('components.layouts.app', [
            'fullWidth' => true,
            'title' => 'Edit Template: ' . $this->template->name,
            'breadcrumbs' => [
                ['label' => $this->template->isGlobal() && auth()->user()?->is_super_admin ? 'Template Global' : 'Library Template', 'url' => $backUrl],
                ['label' => $this->template->name],
            ],
        ]);
    }
}
