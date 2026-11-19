<?php

namespace App\Livewire\Bulk;

use App\Actions\Bulk\ImportRecipientsAction;
use App\Actions\Bulk\StartBulkGenerationAction;
use App\Enums\LetterStatus;
use App\Exceptions\DomainException;
use App\Models\BulkJob;
use App\Models\Letter;
use App\Models\LetterTemplate;
use App\Services\Templates\VariableTypeResolver;
use App\Support\CurrentWorkspace;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Bulk Generation (Mailing)')]
class BulkGenerator extends Component
{
    use WithFileUploads;

    public ?int $templateId = null;

    public ?int $sourceLetterId = null;

    public string $kategori = 'SU';

    public string $customKategori = '';

    public string $parameter = '-i';

    public string $customParameter = '';

    public bool $isKegiatan = true;

    public string $kodeKegiatan = 'OASIS';

    public string $perihal = '';

    public string $letterDate = '';

    /** @var array<string, mixed> */
    public array $sharedVariables = [];

    public string $inputTab = 'manual';

    /** @var array<int, string> */
    public array $varyingFieldNames = [];

    /** @var array<int, array<string, string>> */
    public array $manualRows = [];

    public $recipientsFile = null;

    /** @var array<int, array<string, string>> */
    public array $uploadedRows = [];

    /** @var array<int, array{row: int, errors: array<int, string>}> */
    public array $uploadFailedRows = [];

    public ?string $errorMessage = null;

    public bool $showBulkModal = false;

    public function mount(VariableTypeResolver $resolver): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        if (! $ws || ! $ws->roleOf(auth()->user())?->canWrite()) {
            abort(403, 'Peran Ketua bersifat read-only dan tidak dapat menjalankan bulk generation.');
        }

        $this->letterDate = now()->toDateString();

        $fromLetterId = request()->query('from_letter');
        if ($fromLetterId) {
            $this->loadFromLetter((int) $fromLetterId, $resolver);
            $this->showBulkModal = true;
        } else {
            $first = LetterTemplate::where('is_deprecated', false)->orderBy('id')->first();
            if ($first) {
                $this->templateId = $first->id;
                $this->perihal = $first->name;
                $this->sharedVariables['perihal'] = $first->name;
                $this->syncVaryingFieldsForTemplate($resolver);
            } else {
                $this->varyingFieldNames = ['penerima', 'tempat_tujuan'];
                $this->manualRows = [
                    ['penerima' => 'Dr. Budi Santoso, M.Kom.', 'tempat_tujuan' => 'Fakultas Teknik'],
                    ['penerima' => 'Ir. Siti Aminah, M.T.', 'tempat_tujuan' => 'Fakultas Sains'],
                ];
            }
        }

        auth()->user()?->unreadNotifications->markAsRead();
    }

    public function updatedSourceLetterId(): void
    {
        if ($this->sourceLetterId) {
            $this->loadFromLetter((int) $this->sourceLetterId, app(VariableTypeResolver::class));
        }
    }

    public function loadFromLetter(int $letterId, VariableTypeResolver $resolver): void
    {
        $letter = Letter::withoutGlobalScopes()->find($letterId);
        if (! $letter) {
            return;
        }

        $this->sourceLetterId = $letter->id;
        $this->templateId = $letter->template_id;
        $this->perihal = $letter->perihal;
        $this->letterDate = $letter->letter_date?->toDateString() ?? now()->toDateString();

        $this->syncVaryingFieldsForTemplate($resolver);

        $existingValues = $letter->field_values ?? [];
        $this->kategori = (string) ($existingValues['_kategori'] ?? 'SU');
        $this->parameter = (string) ($existingValues['_parameter'] ?? '-i');
        $this->kodeKegiatan = (string) ($existingValues['_kode_kegiatan'] ?? 'OASIS');
        $this->isKegiatan = $this->kodeKegiatan !== '';

        foreach ($existingValues as $k => $v) {
            if (! str_starts_with((string) $k, '_') && ! in_array($k, $this->varyingFieldNames, true)) {
                $this->sharedVariables[$k] = $v;
            }
        }
        $this->sharedVariables['perihal'] = $this->perihal;

        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Data Surat Dimuat',
            'description' => "Template dan variabel disalin dari surat: {$letter->formatted_number} ({$letter->perihal})",
        ]);
    }

    public function updatedTemplateId(): void
    {
        $resolver = app(VariableTypeResolver::class);
        $selected = $this->templateId ? LetterTemplate::withoutGlobalScopes()->find($this->templateId) : null;
        if ($selected) {
            $this->perihal = $selected->name;
            $this->sharedVariables['perihal'] = $selected->name;
        }
        $this->syncVaryingFieldsForTemplate($resolver);
    }

    public function updatedPerihal(): void
    {
        $this->sharedVariables['perihal'] = $this->perihal;
    }

    public function updatedSharedVariables(): void
    {
        if (isset($this->sharedVariables['perihal']) && trim((string) $this->sharedVariables['perihal']) !== '') {
            $this->perihal = (string) $this->sharedVariables['perihal'];
        }
    }

    private function syncVaryingFieldsForTemplate(VariableTypeResolver $resolver): void
    {
        $selected = $this->templateId ? LetterTemplate::withoutGlobalScopes()->find($this->templateId) : null;
        if ($selected && trim($this->perihal) === '') {
            $this->perihal = $selected->name;
        }

        $fields = $this->getTemplateEditableFields($selected, $resolver);
        $fieldNames = array_column($fields, 'name');

        if (empty($fieldNames)) {
            $this->varyingFieldNames = ['penerima', 'tempat_tujuan'];
            $this->manualRows = [['penerima' => '', 'tempat_tujuan' => '']];

            return;
        }

        // Smart detect recipient-related fields in the template
        $preferredKeywords = [
            'tujuan', 'penerima', 'nama', 'instansi', 'tempat_tujuan',
            'alamat', 'jabatan', 'departemen', 'email', 'nohp',
        ];

        $matched = [];
        foreach ($preferredKeywords as $kw) {
            if (in_array($kw, $fieldNames, true) && ! in_array($kw, $matched, true)) {
                $matched[] = $kw;
            }
        }

        // If no typical keywords matched, default to first 1 or 2 fields
        $this->varyingFieldNames = ! empty($matched)
            ? $matched
            : array_slice($fieldNames, 0, min(2, count($fieldNames)));

        // Reset manual rows with new fields
        $rowTemplate = [];
        foreach ($this->varyingFieldNames as $fn) {
            $rowTemplate[$fn] = '';
        }

        $sample1 = $rowTemplate;
        $sample2 = $rowTemplate;

        // Provide helpful realistic dummy data for common fields
        if (isset($sample1['tujuan'])) {
            $sample1['tujuan'] = 'Dr. Budi Santoso, M.Kom.';
        }
        if (isset($sample1['penerima'])) {
            $sample1['penerima'] = 'Dr. Budi Santoso, M.Kom.';
        }
        if (isset($sample1['nama'])) {
            $sample1['nama'] = 'Dr. Budi Santoso, M.Kom.';
        }

        if (isset($sample1['instansi'])) {
            $sample1['instansi'] = 'Fakultas Teknik';
        }
        if (isset($sample1['tempat_tujuan'])) {
            $sample1['tempat_tujuan'] = 'Fakultas Teknik';
        }
        if (isset($sample1['alamat'])) {
            $sample1['alamat'] = 'Fakultas Teknik';
        }

        if (isset($sample2['tujuan'])) {
            $sample2['tujuan'] = 'Ir. Siti Aminah, M.T.';
        }
        if (isset($sample2['penerima'])) {
            $sample2['penerima'] = 'Ir. Siti Aminah, M.T.';
        }
        if (isset($sample2['nama'])) {
            $sample2['nama'] = 'Ir. Siti Aminah, M.T.';
        }

        if (isset($sample2['instansi'])) {
            $sample2['instansi'] = 'Fakultas Sains';
        }
        if (isset($sample2['tempat_tujuan'])) {
            $sample2['tempat_tujuan'] = 'Fakultas Sains';
        }
        if (isset($sample2['alamat'])) {
            $sample2['alamat'] = 'Fakultas Sains';
        }

        $this->manualRows = [$sample1, $sample2];
    }

    public function toggleVaryingField(string $fieldName): void
    {
        if (in_array($fieldName, $this->varyingFieldNames, true)) {
            if (count($this->varyingFieldNames) > 1) {
                $this->varyingFieldNames = array_values(array_diff($this->varyingFieldNames, [$fieldName]));
                // Clean up removed field from manual rows so old values don't linger
                foreach ($this->manualRows as $idx => $r) {
                    unset($this->manualRows[$idx][$fieldName]);
                }
            } else {
                $this->dispatch('toast', [
                    'type' => 'warning',
                    'title' => 'Minimal 1 variabel multi-isi',
                    'description' => 'Surat massal memerlukan minimal satu variabel yang berbeda tiap surat.',
                ]);
            }
        } else {
            $this->varyingFieldNames[] = $fieldName;
            // Clean up from sharedVariables so it doesn't conflict
            unset($this->sharedVariables[$fieldName]);
            foreach ($this->manualRows as $idx => $r) {
                if (! isset($this->manualRows[$idx][$fieldName])) {
                    $this->manualRows[$idx][$fieldName] = '';
                }
            }
        }
    }

    public function addManualRow(): void
    {
        $newRow = [];
        foreach ($this->varyingFieldNames as $fn) {
            $newRow[$fn] = '';
        }
        $this->manualRows[] = $newRow;
    }

    public function removeManualRow(int $idx): void
    {
        if (isset($this->manualRows[$idx])) {
            unset($this->manualRows[$idx]);
            $this->manualRows = array_values($this->manualRows);
        }
    }

    public function updatedRecipientsFile(ImportRecipientsAction $importer): void
    {
        $this->uploadedRows = [];
        $this->uploadFailedRows = [];
        $this->errorMessage = null;

        if (! $this->recipientsFile) {
            return;
        }

        try {
            $parsed = $importer->fromFile($this->recipientsFile->getRealPath());
            $this->uploadedRows = $parsed['valid'];
            $this->uploadFailedRows = $parsed['failed'];
        } catch (DomainException $e) {
            $this->errorMessage = $e->getMessage();
            $this->uploadFailedRows = $e->details['failed_rows'] ?? [];
        }
    }

    public function startBulk(StartBulkGenerationAction $action): void
    {
        $this->errorMessage = null;
        $ws = app(CurrentWorkspace::class)->get();

        $this->validate([
            'templateId' => ['required', 'integer'],
            'perihal' => ['required', 'string', 'max:255'],
            'letterDate' => ['required', 'date'],
        ], [
            'templateId.required' => 'Pilih template surat terlebih dahulu.',
            'perihal.required' => 'Perihal surat wajib diisi.',
            'letterDate.required' => 'Tanggal surat wajib diisi.',
        ]);

        $combinedItems = $this->combinedQueue();
        if (empty($combinedItems)) {
            $this->errorMessage = 'Tambahkan minimal satu penerima yang valid (manual atau melalui upload file CSV/XLSX).';

            return;
        }

        if (! in_array('perihal', $this->varyingFieldNames, true)) {
            $this->sharedVariables['perihal'] = $this->perihal;
        }

        $this->sharedVariables['_kategori'] = $this->getEffectiveKategori();
        $this->sharedVariables['_parameter'] = $this->getEffectiveParameter();
        $this->sharedVariables['_kode_kegiatan'] = $this->getEffectiveKodeKegiatan();

        try {
            $job = $action->execute($ws, auth()->user(), [
                'template_id' => $this->templateId,
                'perihal' => $this->perihal,
                'letter_date' => $this->letterDate,
                'shared_variables' => $this->sharedVariables,
                'recipients' => [
                    'mode' => 'manual',
                    'items' => $combinedItems,
                ],
            ]);

            // In local/development environment, trigger queue worker immediately so the user doesn't wait
            try {
                \Illuminate\Support\Facades\Artisan::call('queue:work', [
                    '--stop-when-empty' => true,
                ]);
            } catch (\Throwable) {
                // Ignore if queue worker is already running externally
            }

            $this->dispatch('toast', [
                'type' => 'success',
                'title' => 'Job bulk generation dijalankan',
                'description' => "{$job->recipient_count} penerima diproses dalam antrean.",
            ]);

            $this->showBulkModal = false;
        } catch (DomainException $e) {
            $this->errorMessage = $e->getMessage();
        } catch (\Throwable $e) {
            $this->errorMessage = 'Terjadi kesalahan saat memproses bulk generate: '.$e->getMessage();
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function combinedQueue(): array
    {
        $manualValid = array_values(array_filter(
            $this->manualRows,
            function (array $r): bool {
                foreach ($this->varyingFieldNames as $fn) {
                    if (trim((string) ($r[$fn] ?? '')) !== '') {
                        return true;
                    }
                }

                return false;
            }
        ));

        if ($this->inputTab === 'upload' && ! empty($this->uploadedRows)) {
            return $this->uploadedRows;
        }

        return array_merge($manualValid, $this->uploadedRows);
    }

    /**
     * @return array<int, array{name: string, label: string, type: string, required: bool}>
     */
    private function getTemplateEditableFields(?LetterTemplate $template, VariableTypeResolver $resolver): array
    {
        if (! $template) {
            return [];
        }

        return array_values(array_filter(
            $resolver->editableFields($template->variables_schema ?? [], includePerihal: true),
            fn ($f) => ($f['type'] ?? 'text') !== 'repeater'
        ));
    }

    public function openBulkModal(): void
    {
        $this->resetErrorBag();
        $this->errorMessage = null;
        $this->showBulkModal = true;
    }

    public function closeBulkModal(): void
    {
        $this->showBulkModal = false;
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
        $templates = LetterTemplate::where('is_deprecated', false)->orderBy('name')->get();
        $selectedTemplate = $this->templateId ? LetterTemplate::withoutGlobalScopes()->find($this->templateId) : null;
        $allEditableFields = $this->getTemplateEditableFields($selectedTemplate, $resolver);

        $varyingFields = array_values(array_filter(
            $allEditableFields,
            fn ($f) => in_array($f['name'], $this->varyingFieldNames, true)
        ));

        $sharedFields = array_values(array_filter(
            $allEditableFields,
            fn ($f) => ! in_array($f['name'], $this->varyingFieldNames, true)
        ));

        $jobs = BulkJob::with('template')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $recentLetters = Letter::where('workspace_id', $ws?->id)
            ->where('status', '!=', LetterStatus::Dibatalkan)
            ->orderByDesc('id')
            ->limit(20)
            ->get();

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

        return view('livewire.bulk.bulk-generator', [
            'workspace' => $ws,
            'templates' => $templates,
            'recentLetters' => $recentLetters,
            'selectedTemplate' => $selectedTemplate,
            'allEditableFields' => $allEditableFields,
            'varyingFields' => $varyingFields,
            'sharedFields' => $sharedFields,
            'queueItems' => $this->combinedQueue(),
            'jobs' => $jobs,
            'letterTypes' => $ws?->letterTypes() ?? config('suratinaja.letter_types', []),
            'letterParameters' => config('suratinaja.letter_parameters', []),
            'liveNumberPreview' => $liveNumberPreview,
        ]);
    }
}
