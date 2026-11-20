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

    public int $nextSequence = 1;

    public int $initialNextSequence = 1;

    public int $minAllowedSequence = 1;

    public bool $showConfirmModal = false;

    public function mount(): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        $user = auth()->user();
        $role = $ws ? $ws->roleOf($user) : null;
        $canManage = $user?->is_super_admin || $user?->can('penomoran.manage') || ($role?->isAdmin() || $role?->value === 'secretary');
        if (! $ws || ! $canManage) {
            abort(403);
        }

        $this->numberPattern = $ws->number_pattern;
        $this->numberCode = (string) ($ws->number_code ?? '');
        $this->activityCode = (string) ($ws->activity_code ?: 'JKBFEST');
        $this->isActivity = (bool) ($ws->is_activity ?? true);
        $this->seqPadding = (int) ($ws->seq_padding ?? 3);

        $periodKey = app(\App\Services\Numbering\NumberAllocator::class)->periodKey(now());
        $maxIssuedSequence = (int) \App\Models\LetterNumber::withoutGlobalScopes()
            ->where('workspace_id', $ws->id)
            ->where('period_key', $periodKey)
            ->max('sequence');

        $seqRow = \Illuminate\Support\Facades\DB::table('letter_number_sequences')
            ->where('workspace_id', $ws->id)
            ->where('period_key', $periodKey)
            ->first();

        $lastSeq = $seqRow ? (int) $seqRow->last_sequence : $maxIssuedSequence;

        $this->minAllowedSequence = $maxIssuedSequence > 0 ? ($maxIssuedSequence + 1) : 1;
        $this->nextSequence = max($this->minAllowedSequence, $lastSeq + 1);
        $this->initialNextSequence = $this->nextSequence;
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
        $user = auth()->user();
        $role = $ws ? $ws->roleOf($user) : null;
        $canManage = $user?->is_super_admin || $user?->can('penomoran.manage') || ($role?->isAdmin() || $role?->value === 'secretary');
        if (! $ws || ! $canManage) {
            abort(403);
        }

        $this->validate([
            'numberPattern' => ['required', 'string', 'max:255'],
            'numberCode' => ['required', 'string', 'max:40'],
            'activityCode' => ['nullable', 'string', 'max:50'],
            'isActivity' => ['boolean'],
            'seqPadding' => ['required', 'integer', 'min:1', 'max:6'],
            'nextSequence' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $renderer->validate($this->numberPattern);
        } catch (DomainException $e) {
            $this->addError('numberPattern', $e->getMessage());

            return;
        }

        // Validasi ketat nomor urut minimum
        if ($this->nextSequence < $this->minAllowedSequence) {
            $pad = $this->seqPadding;
            $formattedMin = str_pad((string) max(1, $this->minAllowedSequence - 1), $pad, '0', STR_PAD_LEFT);
            $this->addError(
                'nextSequence',
                "Nomor urut tidak boleh lebih rendah dari nomor surat terakhir yang sudah terbit (Nomor: {$formattedMin}). Batas minimum: {$this->minAllowedSequence}."
            );

            return;
        }

        // Jika nomor urut berubah, munculkan modal konfirmasi terlebih dahulu
        if ($this->nextSequence !== $this->initialNextSequence) {
            $this->showConfirmModal = true;

            return;
        }

        $this->performSave($renderer);
    }

    public function confirmSave(NumberPatternRenderer $renderer): void
    {
        $this->performSave($renderer, true);
    }

    private function performSave(NumberPatternRenderer $renderer, bool $sequenceChanged = false): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        $user = auth()->user();

        $ws->fill([
            'number_pattern' => trim($this->numberPattern),
            'number_code' => trim($this->numberCode),
            'activity_code' => $this->isActivity ? strtoupper(trim($this->activityCode)) : null,
            'is_activity' => $this->isActivity,
            'seq_padding' => $this->seqPadding,
        ])->save();

        $periodKey = app(\App\Services\Numbering\NumberAllocator::class)->periodKey(now());
        \Illuminate\Support\Facades\DB::table('letter_number_sequences')->updateOrInsert(
            [
                'workspace_id' => $ws->id,
                'period_key' => $periodKey,
            ],
            [
                'last_sequence' => max(0, $this->nextSequence - 1),
                'updated_at' => now(),
            ]
        );

        if ($sequenceChanged || $this->nextSequence !== $this->initialNextSequence) {
            \App\Models\WorkspaceAuditLog::create([
                'workspace_id' => $ws->id,
                'user_id' => $user?->id,
                'action' => 'numbering_sequence_changed',
                'description' => "Nomor urut berikutnya diubah dari {$this->initialNextSequence} menjadi {$this->nextSequence}.",
                'payload' => [
                    'old_sequence' => $this->initialNextSequence,
                    'new_sequence' => $this->nextSequence,
                    'min_allowed' => $this->minAllowedSequence,
                    'period_key' => $periodKey,
                    'saved_last_sequence' => max(0, $this->nextSequence - 1),
                ],
                'created_at' => now(),
            ]);

            $this->initialNextSequence = $this->nextSequence;
        }

        $this->showConfirmModal = false;

        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Pola penomoran & nomor awal disimpan',
            'description' => 'Konfigurasi penomoran berhasil diperbarui.',
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
            $preview = $renderer->render($this->numberPattern, $tempWs, $this->nextSequence, $sampleDate, [
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
        ])->layout('components.layouts.app', [
            'title' => 'Pola Penomoran Surat',
            'breadcrumbs' => [
                ['label' => 'Konfigurasi', 'url' => route('settings.numbering')],
                ['label' => 'Pola Penomoran'],
            ],
        ]);
    }
}
