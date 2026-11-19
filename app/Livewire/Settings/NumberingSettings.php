<?php

namespace App\Livewire\Settings;

use App\Exceptions\DomainException;
use App\Services\Numbering\NumberPatternRenderer;
use App\Support\CurrentWorkspace;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Pola Penomoran Surat')]
class NumberingSettings extends Component
{
    public string $numberPattern = '';

    public string $numberCode = '';

    public string $activityCode = 'JKBFEST';

    public bool $isActivity = true;

    public int $seqPadding = 3;

    public function mount(): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        if (! $ws || ! $ws->roleOf(auth()->user())?->isAdmin()) {
            abort(403);
        }

        $this->numberPattern = $ws->number_pattern;
        $this->numberCode = (string) ($ws->number_code ?? '');
        $this->activityCode = (string) ($ws->activity_code ?: 'JKBFEST');
        $this->isActivity = (bool) ($ws->is_activity ?? true);
        $this->seqPadding = (int) ($ws->seq_padding ?? 3);
    }

    public function applyPreset(string $presetKey): void
    {
        $preset = config("suratinaja.numbering.presets.{$presetKey}");
        if ($preset && isset($preset['pattern'])) {
            $this->numberPattern = $preset['pattern'];
            $this->dispatch('toast', [
                'type' => 'info',
                'title' => 'Preset Diterapkan',
                'description' => "Pola diatur ke: {$preset['name']}",
            ]);
        }
    }

    public function save(NumberPatternRenderer $renderer): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        if (! $ws || ! $ws->roleOf(auth()->user())?->isAdmin()) {
            abort(403);
        }

        $this->validate([
            'numberPattern' => ['required', 'string', 'max:255'],
            'numberCode' => ['required', 'string', 'max:40'],
            'activityCode' => ['nullable', 'string', 'max:50'],
            'isActivity' => ['boolean'],
            'seqPadding' => ['required', 'integer', 'min:1', 'max:6'],
        ]);

        try {
            $renderer->validate($this->numberPattern);
        } catch (DomainException $e) {
            $this->addError('numberPattern', $e->getMessage());

            return;
        }

        $ws->fill([
            'number_pattern' => trim($this->numberPattern),
            'number_code' => trim($this->numberCode),
            'activity_code' => $this->isActivity ? strtoupper(trim($this->activityCode)) : null,
            'is_activity' => $this->isActivity,
            'seq_padding' => $this->seqPadding,
        ])->save();

        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Pola penomoran disimpan',
        ]);
    }

    public function render(NumberPatternRenderer $renderer)
    {
        $ws = app(CurrentWorkspace::class)->get();
        $preview = null;
        $previewError = null;

        try {
            $tempWs = clone $ws;
            $tempWs->number_pattern = $this->numberPattern;
            $tempWs->number_code = $this->numberCode;
            $tempWs->activity_code = $this->activityCode;
            $tempWs->is_activity = $this->isActivity;
            $tempWs->seq_padding = $this->seqPadding;
            $sampleDate = \Carbon\Carbon::create(2026, 7, 15);
            $preview = $renderer->render($this->numberPattern, $tempWs, 1, $sampleDate, [
                'kategori' => 'SPm',
                'parameter' => '-i',
                'kode_kegiatan' => $this->isActivity ? strtoupper(trim($this->activityCode)) : '',
            ]);
        } catch (DomainException $e) {
            $previewError = $e->getMessage();
        }

        $presets = config('suratinaja.numbering.presets', []);
        $letterTypes = config('suratinaja.letter_types', []);
        $letterParameters = config('suratinaja.letter_parameters', []);

        return view('livewire.settings.numbering-settings', [
            'workspace' => $ws,
            'preview' => $preview,
            'previewError' => $previewError,
            'presets' => $presets,
            'letterTypes' => $letterTypes,
            'letterParameters' => $letterParameters,
        ]);
    }
}
