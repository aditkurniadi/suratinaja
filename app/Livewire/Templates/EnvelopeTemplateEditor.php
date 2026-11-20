<?php

namespace App\Livewire\Templates;

use App\Models\EnvelopeTemplate;
use App\Services\Documents\OnlyOfficeService;
use App\Support\CurrentWorkspace;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Editor Template Amplop')]
class EnvelopeTemplateEditor extends Component
{
    public EnvelopeTemplate $envelopeTemplate;
    public bool $canEdit = false;
    public array $editorConfig = [];
    public string $docServerUrl = '';

    public function mount(EnvelopeTemplate $envelopeTemplate, OnlyOfficeService $onlyOfficeService)
    {
        $workspace = app(CurrentWorkspace::class)->get();
        $user = auth()->user();

        if (! $workspace || (int) $envelopeTemplate->workspace_id !== (int) $workspace->id) {
            abort(403, 'Anda tidak memiliki akses ke template amplop ini.');
        }

        $this->envelopeTemplate = $envelopeTemplate;
        $this->canEdit = $workspace->roleOf($user)?->canWrite() === true;

        $this->editorConfig = $onlyOfficeService->buildEnvelopeEditorConfig($envelopeTemplate, $user, $this->canEdit);
        $this->docServerUrl = rtrim((string) config('suratinaja.onlyoffice.url', 'http://127.0.0.1:8082'), '/');
    }

    public function render()
    {
        return view('livewire.templates.envelope-template-editor')
            ->layout('components.layouts.app', [
                'title' => 'Edit Master Amplop - ' . $this->envelopeTemplate->name,
                'breadcrumbs' => [
                    ['label' => 'Konfigurasi', 'url' => route('settings.kop')],
                    ['label' => 'Master Template Amplop', 'url' => route('settings.envelopes')],
                    ['label' => $this->envelopeTemplate->name],
                ],
                'backUrl' => route('settings.envelopes'),
            ]);
    }
}
