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

    public int $seqPadding = 3;

    public function mount(): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        if (! $ws || ! $ws->roleOf(auth()->user())?->isAdmin()) {
            abort(403);
        }

        $this->numberPattern = $ws->number_pattern;
        $this->numberCode = (string) ($ws->number_code ?? '');
        $this->seqPadding = (int) ($ws->seq_padding ?? 3);
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
            $tempWs->seq_padding = $this->seqPadding;
            $preview = $renderer->render($this->numberPattern, $tempWs, 12, now());
        } catch (DomainException $e) {
            $previewError = $e->getMessage();
        }

        return view('livewire.settings.numbering-settings', [
            'workspace' => $ws,
            'preview' => $preview,
            'previewError' => $previewError,
        ]);
    }
}
