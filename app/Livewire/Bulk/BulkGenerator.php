<?php

namespace App\Livewire\Bulk;

use App\Actions\Bulk\ImportRecipientsAction;
use App\Actions\Bulk\StartBulkGenerationAction;
use App\Exceptions\DomainException;
use App\Models\BulkJob;
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

    public string $perihal = '';

    public string $letterDate = '';

    /** @var array<string, mixed> */
    public array $sharedVariables = [];

    public string $inputTab = 'manual';

    /** @var array<int, array{penerima: string, tempat_tujuan: string}> */
    public array $manualRows = [];

    public $recipientsFile = null;

    /** @var array<int, array<string, string>> */
    public array $uploadedRows = [];

    /** @var array<int, array{row: int, errors: array<int, string>}> */
    public array $uploadFailedRows = [];

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        if (! $ws || ! $ws->roleOf(auth()->user())?->canWrite()) {
            abort(403, 'Peran Ketua bersifat read-only dan tidak dapat menjalankan bulk generation.');
        }

        $this->letterDate = now()->toDateString();
        $this->manualRows = [
            ['penerima' => 'Dr. Budi Santoso, M.Kom.', 'tempat_tujuan' => 'Fakultas Teknik'],
            ['penerima' => 'Ir. Siti Aminah, M.T.', 'tempat_tujuan' => 'Fakultas Sains'],
        ];

        $first = LetterTemplate::where('is_deprecated', false)->orderBy('id')->first();
        if ($first) {
            $this->templateId = $first->id;
        }

        auth()->user()?->unreadNotifications->markAsRead();
    }

    public function addManualRow(): void
    {
        $this->manualRows[] = ['penerima' => '', 'tempat_tujuan' => ''];
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
        ]);

        $combinedItems = $this->combinedQueue();
        if (empty($combinedItems)) {
            $this->errorMessage = 'Tambahkan minimal satu penerima yang valid (manual atau melalui upload file CSV/XLSX).';

            return;
        }

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

            $this->dispatch('toast', [
                'type' => 'success',
                'title' => 'Job bulk generation dijalankan',
                'description' => "{$job->recipient_count} penerima diproses dalam antrean.",
            ]);
        } catch (DomainException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function combinedQueue(): array
    {
        $manualValid = array_values(array_filter(
            $this->manualRows,
            fn ($r) => trim((string) ($r['penerima'] ?? '')) !== ''
        ));

        if ($this->inputTab === 'upload' && ! empty($this->uploadedRows)) {
            return $this->uploadedRows;
        }

        return array_merge($manualValid, $this->uploadedRows);
    }

    public function render(VariableTypeResolver $resolver)
    {
        $ws = app(CurrentWorkspace::class)->get();
        $templates = LetterTemplate::where('is_deprecated', false)->orderBy('name')->get();
        $selectedTemplate = $this->templateId ? LetterTemplate::withoutGlobalScopes()->find($this->templateId) : null;
        $editableFields = $selectedTemplate
            ? array_values(array_filter(
                $resolver->editableFields($selectedTemplate->variables_schema ?? []),
                fn ($f) => ! in_array($f['name'], ['penerima', 'tempat_tujuan'], true) && ($f['type'] ?? 'text') !== 'repeater'
            ))
            : [];

        $jobs = BulkJob::with('template')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('livewire.bulk.bulk-generator', [
            'workspace' => $ws,
            'templates' => $templates,
            'editableFields' => $editableFields,
            'queueItems' => $this->combinedQueue(),
            'jobs' => $jobs,
        ]);
    }
}
