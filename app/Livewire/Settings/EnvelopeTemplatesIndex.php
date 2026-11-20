<?php

namespace App\Livewire\Settings;

use App\Models\EnvelopeTemplate;
use App\Models\Workspace;
use App\Services\Templates\EnvelopeStarterDocxBuilder;
use App\Support\CurrentWorkspace;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class EnvelopeTemplatesIndex extends Component
{
    use WithFileUploads;

    public $docxFile;
    public string $templateName = '';
    public string $paperSize = 'dl';
    public bool $showUploadModal = false;

    public function createStarter(string $size = 'dl', EnvelopeStarterDocxBuilder $builder = null)
    {
        $workspace = app(CurrentWorkspace::class)->get();
        if (! $workspace) {
            return;
        }

        $builder = $builder ?? app(EnvelopeStarterDocxBuilder::class);
        $sizeLabel = match ($size) {
            'c5' => 'Amplop Sedang (C5 229x162mm)',
            'a4' => 'Amplop Besar (A4/Map)',
            default => 'Amplop DL / Standar No. 90 (230x110mm)',
        };

        $name = 'Kop ' . $sizeLabel;
        $path = $builder->build($workspace, $size, $name);

        $hasTemplates = EnvelopeTemplate::where('workspace_id', $workspace->id)->exists();

        $template = EnvelopeTemplate::create([
            'workspace_id' => $workspace->id,
            'name' => $name,
            'preset_size' => $size,
            'file_path' => $path,
            'is_default' => ! $hasTemplates,
            'version' => 1,
            'created_by' => auth()->id(),
        ]);

        session()->flash('success', "Starter template {$name} berhasil dibuat!");
        return redirect()->route('settings.envelopes.edit', $template);
    }

    public function uploadTemplate()
    {
        $this->validate([
            'templateName' => 'required|string|max:100',
            'paperSize' => 'required|in:dl,c5,a4,custom',
            'docxFile' => 'required|file|mimes:docx|max:10240',
        ]);

        $workspace = app(CurrentWorkspace::class)->get();
        if (! $workspace) {
            return;
        }

        $filename = 'envelope_' . Str::uuid() . '.docx';
        $path = $this->docxFile->storeAs("workspaces/{$workspace->id}/envelopes", $filename, 'local');

        $hasTemplates = EnvelopeTemplate::where('workspace_id', $workspace->id)->exists();

        $template = EnvelopeTemplate::create([
            'workspace_id' => $workspace->id,
            'name' => $this->templateName,
            'preset_size' => $this->paperSize,
            'file_path' => $path,
            'is_default' => ! $hasTemplates,
            'version' => 1,
            'created_by' => auth()->id(),
        ]);

        $this->reset(['docxFile', 'templateName', 'showUploadModal']);
        session()->flash('success', "Template amplop {$template->name} berhasil diunggah!");

        return redirect()->route('settings.envelopes.edit', $template);
    }

    public function setDefault(int|EnvelopeTemplate $template)
    {
        $workspace = app(CurrentWorkspace::class)->get();
        $target = $template instanceof EnvelopeTemplate ? $template : EnvelopeTemplate::findOrFail($template);
        if ($target->workspace_id !== $workspace->id) {
            return;
        }

        EnvelopeTemplate::where('workspace_id', $workspace->id)->update(['is_default' => false]);
        $target->update(['is_default' => true]);

        session()->flash('success', "Template '{$target->name}' diaktifkan sebagai template default persuratan.");
    }

    public function deleteTemplate(EnvelopeTemplate $template)
    {
        $workspace = app(CurrentWorkspace::class)->get();
        if ($template->workspace_id !== $workspace->id) {
            return;
        }

        if ($template->file_path && Storage::disk('local')->exists($template->file_path)) {
            Storage::disk('local')->delete($template->file_path);
        }

        $wasDefault = $template->is_default;
        $template->delete();

        if ($wasDefault) {
            $first = EnvelopeTemplate::where('workspace_id', $workspace->id)->first();
            if ($first) {
                $first->update(['is_default' => true]);
            }
        }

        session()->flash('success', 'Template amplop berhasil dihapus.');
    }

    public function render()
    {
        $workspace = app(CurrentWorkspace::class)->get();
        $templates = $workspace
            ? EnvelopeTemplate::where('workspace_id', $workspace->id)->orderByDesc('is_default')->latest()->get()
            : collect();

        return view('livewire.settings.envelope-templates-index', [
            'workspace' => $workspace,
            'templates' => $templates,
        ])->layout('components.layouts.app', [
            'title' => 'Master Template Kop Amplop (.docx)',
            'breadcrumbs' => [
                ['label' => 'Konfigurasi', 'url' => route('settings.kop')],
                ['label' => 'Master Template Amplop (.docx)'],
            ],
            'backUrl' => route('settings.kop'),
        ]);
    }
}
