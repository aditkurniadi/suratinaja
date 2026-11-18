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
    public ?int $letterId = null;

    public ?int $templateId = null;

    public string $perihal = '';

    public string $letterDate = '';

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
            $this->perihal = $existing->perihal;
            $this->letterDate = $existing->letter_date?->toDateString() ?? now()->toDateString();
            $this->values = $existing->field_values ?? [];
            $this->initializeTemplateDefaults();

            return;
        }

        $this->letterDate = now()->toDateString();
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

    public function save(CreateLetterAction $createAction, UpdateDraftAction $updateAction): void
    {
        $this->errorMessage = null;
        $ws = app(CurrentWorkspace::class)->get();

        $this->validate([
            'templateId' => ['required', 'integer'],
            'perihal' => ['required', 'string', 'max:255'],
            'letterDate' => ['required', 'date'],
        ]);

        try {
            if ($this->letterId !== null) {
                $existing = Letter::findOrFail($this->letterId);
                $letter = $updateAction->execute($existing, auth()->user(), [
                    'perihal' => $this->perihal,
                    'letter_date' => $this->letterDate,
                    'variables' => $this->values,
                ]);
            } else {
                $letter = $createAction->execute($ws, auth()->user(), [
                    'template_id' => $this->templateId,
                    'perihal' => $this->perihal,
                    'letter_date' => $this->letterDate,
                    'variables' => $this->values,
                ]);
            }
        } catch (DomainException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        }

        $this->redirect(route('letters.show', $letter->id), navigate: false);
    }

    public function render(VariableTypeResolver $resolver)
    {
        $ws = app(CurrentWorkspace::class)->get();
        $templates = LetterTemplate::where('is_deprecated', false)->orderBy('scope')->orderBy('name')->get();
        $selectedTemplate = $this->selectedTemplate();
        $editableFields = $selectedTemplate
            ? $resolver->editableFields($selectedTemplate->variables_schema ?? [])
            : [];

        return view('livewire.letters.letter-composer', [
            'workspace' => $ws,
            'templates' => $templates,
            'selectedTemplate' => $selectedTemplate,
            'editableFields' => $editableFields,
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
