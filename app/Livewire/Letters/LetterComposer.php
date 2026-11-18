<?php

namespace App\Livewire\Letters;

use App\Actions\Letters\CreateLetterAction;
use App\Actions\Letters\UpdateDraftAction;
use App\Enums\LetterStatus;
use App\Exceptions\DomainException;
use App\Models\Letter;
use App\Models\LetterTemplate;
use App\Services\Templates\VariableTypeResolver;
use App\Support\CurrentWorkspace;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Komposisi Surat')]
class LetterComposer extends Component
{
    public int $currentStep = 1;

    public string $letterMode = 'template'; // 'template' | 'manual_number'

    public ?int $letterId = null;

    public ?int $templateId = null;

    public string $kategori = 'SU';

    public string $customKategori = '';

    public string $parameter = '-i';

    public string $customParameter = '';

    public bool $isKegiatan = true;

    public string $kodeKegiatan = 'OASIS';

    public string $perihal = '';

    public string $letterDate = '';

    public string $city = 'Cilacap';

    // Mode Ambil Nomor Saja / Dokumen Eksternal
    public string $linkDrive = '';

    public string $recipientName = '';

    public string $recipientAgency = '';

    public string $manualNotes = '';

    /** @var array<int, array{position: string, name: string, id_label: string, id_number: string}> */
    public array $signers = [];

    /** @var array<string, mixed> */
    public array $values = [];

    public ?string $errorMessage = null;

    public function mount(Letter|int|null $letter = null): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        if (! $ws || ! $ws->roleOf(auth()->user())?->canWrite()) {
            abort(403, 'Peran Ketua bersifat read-only dan tidak dapat membuat atau mengedit surat.');
        }

        if ($letter !== null) {
            $existing = $letter instanceof Letter ? $letter : Letter::findOrFail($letter);
            if ($existing->status !== LetterStatus::Draft) {
                $this->redirect(route('letters.show', $existing->id), navigate: false);

                return;
            }
            $this->letterId = $existing->id;
            $this->templateId = $existing->template_id;
            $this->letterMode = $existing->is_manual_number || ! $existing->template_id ? 'manual_number' : 'template';
            $this->linkDrive = $existing->link_drive ?? '';
            $this->perihal = $existing->perihal;
            $this->letterDate = $existing->letter_date?->toDateString() ?? now()->toDateString();
            $this->values = $existing->field_values ?? [];
            $this->recipientName = (string) ($this->values['penerima'] ?? '');
            $this->recipientAgency = (string) ($this->values['instansi'] ?? '');
            $this->manualNotes = (string) ($this->values['catatan'] ?? '');
            $this->kategori = (string) ($this->values['_kategori'] ?? 'SU');
            $this->parameter = (string) ($this->values['_parameter'] ?? '-i');
            $this->kodeKegiatan = (string) ($this->values['_kode_kegiatan'] ?? ($ws->activity_code ?: 'OASIS'));
            $this->isKegiatan = $ws->is_activity;
            $this->city = (string) ($this->values['_city'] ?? $ws->default_city ?: 'Cilacap');
            $this->signers = is_array($this->values['_signers'] ?? null) && count($this->values['_signers']) > 0
                ? array_values(array_map(fn (array $s) => [
                    'prefix' => (string) ($s['prefix'] ?? ''),
                    'position' => (string) ($s['position'] ?? ''),
                    'name' => (string) ($s['name'] ?? ''),
                    'id_label' => (string) ($s['id_label'] ?? 'NIP.'),
                    'id_number' => (string) ($s['id_number'] ?? ''),
                    'layout' => (string) ($s['layout'] ?? 'auto'),
                ], $this->values['_signers']))
                : $ws->defaultSigners();
            $this->initializeTemplateDefaults();

            return;
        }

        $this->letterDate = now()->toDateString();
        $this->city = (string) ($ws->default_city ?: 'Cilacap');
        $this->signers = $ws->defaultSigners();
        $this->isKegiatan = $ws->is_activity;
        if (! empty($ws->activity_code)) {
            $this->kodeKegiatan = $ws->activity_code;
        }

        $preselected = request()->query('template_id');
        if ($preselected) {
            $this->templateId = (int) $preselected;
            $this->initializeTemplateDefaults();
        } else {
            $first = LetterTemplate::where('is_deprecated', false)->orderBy('id')->first();
            if ($first) {
                $this->templateId = $first->id;
                $this->initializeTemplateDefaults();
            }
        }
    }

    public function updatedTemplateId(): void
    {
        $this->values = [];
        $this->initializeTemplateDefaults();
    }

    public function setLetterMode(string $mode): void
    {
        $this->letterMode = $mode === 'manual_number' ? 'manual_number' : 'template';
        if ($this->letterMode === 'template' && ! $this->templateId) {
            $first = LetterTemplate::where('is_deprecated', false)->orderBy('id')->first();
            if ($first) {
                $this->templateId = $first->id;
                $this->initializeTemplateDefaults();
            }
        }
    }

    public function nextStep(): void
    {
        $this->errorMessage = null;

        if ($this->currentStep === 1) {
            $rules = [
                'perihal' => ['required', 'string', 'max:255'],
                'letterDate' => ['required', 'date'],
                'kategori' => ['required', 'string'],
            ];

            if ($this->letterMode === 'template') {
                $rules['templateId'] = ['required', 'integer'];
            }

            $this->validate($rules, [
                'templateId.required' => 'Pilih template dokumen terlebih dahulu.',
                'perihal.required' => 'Perihal surat wajib diisi.',
                'letterDate.required' => 'Tanggal surat wajib diisi.',
            ]);

            $this->currentStep = 2;

            return;
        }

        if ($this->currentStep === 2) {
            if ($this->letterMode === 'manual_number') {
                $this->validate([
                    'recipientName' => ['required', 'string', 'max:255'],
                    'linkDrive' => ['nullable', 'url', 'max:500'],
                ], [
                    'recipientName.required' => 'Nama penerima / tujuan surat wajib diisi.',
                    'linkDrive.url' => 'Format URL Google Drive tidak valid (harus dimulai dengan https://).',
                ]);
            }

            $this->currentStep = 3;

            return;
        }
    }

    public function prevStep(): void
    {
        $this->errorMessage = null;
        $this->currentStep = max(1, $this->currentStep - 1);
    }

    public function goToStep(int $step): void
    {
        if ($step < $this->currentStep) {
            $this->currentStep = $step;
        } elseif ($step === 2 && $this->currentStep === 1) {
            $this->nextStep();
        } elseif ($step === 3 && $this->currentStep === 2) {
            $this->nextStep();
        }
    }

    public function addRepeaterRow(string $group): void
    {
        $template = $this->selectedTemplate();
        if (! $template) {
            return;
        }

        foreach ($template->variables_schema ?? [] as $field) {
            if (($field['type'] ?? '') === 'repeater' && $field['name'] === $group) {
                $emptyRow = [];
                foreach ($field['columns'] ?? [] as $col) {
                    $emptyRow[$col['name']] = '';
                }
                $this->values[$group][] = $emptyRow;

                return;
            }
        }
    }

    public function removeRepeaterRow(string $group, int $index): void
    {
        if (isset($this->values[$group][$index])) {
            unset($this->values[$group][$index]);
            $this->values[$group] = array_values($this->values[$group]);
        }
    }

    public function addSigner(): void
    {
        $this->signers[] = [
            'prefix' => '',
            'position' => '',
            'name' => '',
            'id_label' => 'NIP.',
            'id_number' => '',
            'layout' => 'auto',
        ];
    }

    public function applyPreset(string $key): void
    {
        $presets = \App\Models\Workspace::signerPresets();
        if (isset($presets[$key])) {
            $this->signers = $presets[$key]['signers'];
        }
    }

    public function removeSigner(int $index): void
    {
        if (isset($this->signers[$index])) {
            unset($this->signers[$index]);
            $this->signers = array_values($this->signers);
        }
    }

    public function moveSigner(int $fromIndex, int $toIndex): void
    {
        if (! isset($this->signers[$fromIndex]) || ! isset($this->signers[$toIndex])) {
            return;
        }

        $item = $this->signers[$fromIndex];
        array_splice($this->signers, $fromIndex, 1);
        array_splice($this->signers, $toIndex, 0, [$item]);
        $this->signers = array_values($this->signers);
    }

    public function moveSignerUp(int $index): void
    {
        if ($index > 0) {
            $this->moveSigner($index, $index - 1);
        }
    }

    public function moveSignerDown(int $index): void
    {
        if ($index < count($this->signers) - 1) {
            $this->moveSigner($index, $index + 1);
        }
    }

    public function save(CreateLetterAction $createAction, UpdateDraftAction $updateAction): void
    {
        $this->errorMessage = null;
        $ws = app(CurrentWorkspace::class)->get();

        $rules = [
            'perihal' => ['required', 'string', 'max:255'],
            'letterDate' => ['required', 'date'],
            'city' => ['nullable', 'string', 'max:100'],
            'signers' => ['nullable', 'array', 'max:12'],
            'signers.*.prefix' => ['nullable', 'string', 'max:60'],
            'signers.*.position' => ['nullable', 'string', 'max:120'],
            'signers.*.name' => ['nullable', 'string', 'max:120'],
            'signers.*.id_label' => ['nullable', 'string', 'max:30'],
            'signers.*.id_number' => ['nullable', 'string', 'max:60'],
            'signers.*.layout' => ['nullable', 'string', 'in:auto,center,new_page'],
        ];

        if ($this->letterMode === 'template') {
            $rules['templateId'] = ['required', 'integer'];
        } else {
            $rules['recipientName'] = ['required', 'string', 'max:255'];
            $rules['linkDrive'] = ['nullable', 'url', 'max:500'];
        }

        $this->validate($rules);

        $cleanSigners = array_values(array_filter(
            array_map(fn (array $s) => [
                'prefix' => trim((string) ($s['prefix'] ?? '')),
                'position' => trim((string) ($s['position'] ?? '')),
                'name' => trim((string) ($s['name'] ?? '')),
                'id_label' => trim((string) ($s['id_label'] ?? '')),
                'id_number' => trim((string) ($s['id_number'] ?? '')),
                'layout' => (string) ($s['layout'] ?? 'auto'),
            ], $this->signers),
            fn (array $s) => $s['name'] !== '' || $s['position'] !== '' || $s['prefix'] !== ''
        ));

        $payloadValues = $this->values;
        $payloadValues['_city'] = trim($this->city) ?: ($ws->default_city ?: 'Cilacap');
        $payloadValues['_signers'] = $cleanSigners !== [] ? $cleanSigners : $ws->defaultSigners();
        $payloadValues['_kategori'] = $this->getEffectiveKategori();
        $payloadValues['_parameter'] = $this->getEffectiveParameter();
        $payloadValues['_kode_kegiatan'] = $this->getEffectiveKodeKegiatan();

        if ($this->letterMode === 'manual_number') {
            $payloadValues['penerima'] = trim($this->recipientName);
            $payloadValues['instansi'] = trim($this->recipientAgency);
            $payloadValues['catatan'] = trim($this->manualNotes);
        }

        try {
            if ($this->letterId !== null) {
                $existing = Letter::findOrFail($this->letterId);
                $letter = $updateAction->execute($existing, auth()->user(), [
                    'perihal' => $this->perihal,
                    'letter_date' => $this->letterDate,
                    'variables' => $payloadValues,
                    'link_drive' => $this->linkDrive ? trim($this->linkDrive) : null,
                ]);
            } else {
                $letter = $createAction->execute($ws, auth()->user(), [
                    'template_id' => $this->letterMode === 'template' ? $this->templateId : null,
                    'is_manual_number' => $this->letterMode === 'manual_number',
                    'link_drive' => $this->linkDrive ? trim($this->linkDrive) : null,
                    'perihal' => $this->perihal,
                    'letter_date' => $this->letterDate,
                    'variables' => $payloadValues,
                ]);
            }
        } catch (DomainException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->redirect(route('letters.show', $letter->id), navigate: false);
    }

    public function getEffectiveKategori(): string
    {
        return $this->kategori === 'custom'
            ? strtoupper(trim($this->customKategori))
            : $this->kategori;
    }

    public function getEffectiveParameter(): string
    {
        return $this->parameter === 'custom'
            ? trim($this->customParameter)
            : $this->parameter;
    }

    public function getEffectiveKodeKegiatan(): string
    {
        $ws = app(CurrentWorkspace::class)->get();
        if ($ws && $ws->is_activity && ! empty($ws->activity_code)) {
            return strtoupper(trim($ws->activity_code));
        }

        return $this->isKegiatan ? strtoupper(trim($this->kodeKegiatan)) : '';
    }

    public function render(VariableTypeResolver $resolver)
    {
        $ws = app(CurrentWorkspace::class)->get();
        $templates = LetterTemplate::where('is_deprecated', false)->orderBy('scope')->orderBy('name')->get();
        $selectedTemplate = $this->selectedTemplate();
        $editableFields = $selectedTemplate
            ? $resolver->editableFields($selectedTemplate->variables_schema ?? [])
            : [];

        $liveNumberPreview = null;
        try {
            $dateObj = \Carbon\Carbon::parse($this->letterDate ?: now());
            $nextSeq = ($ws?->numbers()->max('sequence') ?? 0) + 1;
            $liveNumberPreview = app(\App\Services\Numbering\NumberPatternRenderer::class)->render(
                $ws->number_pattern,
                $ws,
                $nextSeq,
                $dateObj,
                [
                    'kategori' => $this->getEffectiveKategori() ?: 'SU',
                    'parameter' => $this->getEffectiveParameter(),
                    'kode_kegiatan' => $this->getEffectiveKodeKegiatan() ?: 'KEGIATAN',
                ]
            );
        } catch (\Throwable) {
            $liveNumberPreview = null;
        }

        return view('livewire.letters.letter-composer', [
            'workspace' => $ws,
            'templates' => $templates,
            'selectedTemplate' => $selectedTemplate,
            'editableFields' => $editableFields,
            'letterTypes' => $ws?->letterTypes() ?? config('suratinaja.letter_types', []),
            'letterParameters' => config('suratinaja.letter_parameters', []),
            'liveNumberPreview' => $liveNumberPreview,
        ]);
    }

    private function selectedTemplate(): ?LetterTemplate
    {
        if (! $this->templateId) {
            return null;
        }

        $ws = app(CurrentWorkspace::class)->get();
        $template = LetterTemplate::withoutGlobalScopes()->find($this->templateId);

        return ($template && $ws && $template->isAccessibleBy($ws)) ? $template : null;
    }

    private function initializeTemplateDefaults(): void
    {
        $template = $this->selectedTemplate();
        if (! $template) {
            return;
        }

        foreach ($template->variables_schema ?? [] as $field) {
            $type = $field['type'] ?? 'text';
            $name = $field['name'] ?? '';
            if ($name === '' || $type === 'system') {
                continue;
            }
            if ($type === 'repeater') {
                if (! isset($this->values[$name]) || ! is_array($this->values[$name])) {
                    $row = [];
                    foreach ($field['columns'] ?? [] as $col) {
                        $row[$col['name']] = '';
                    }
                    $this->values[$name] = [$row];
                }
            } else {
                $this->values[$name] ??= ($type === 'date' ? now()->toDateString() : '');
            }
        }
    }
}
