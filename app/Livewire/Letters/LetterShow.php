<?php

namespace App\Livewire\Letters;

use App\Actions\Letters\CancelLetterAction;
use App\Actions\Letters\DuplicateLetterAction;
use App\Actions\Letters\ReviseLetterAction;
use App\Actions\Letters\TransitionLetterStatusAction;
use App\Exceptions\DomainException;
use App\Models\Letter;
use App\Services\Documents\OnlyOfficeService;
use App\Services\Templates\VariableTypeResolver;
use App\Support\CurrentWorkspace;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Detail Surat')]
class LetterShow extends Component
{
    public Letter $letter;

    public string $activeRightTab = 'editor';

    public bool $showDetailModal = false;

    public bool $showReviseModal = false;

    public bool $useNewNumber = true;

    public string $revisionReason = '';

    public string $revisionPerihal = '';

    /** @var array<string, mixed> */
    public array $revisionVariables = [];

    public ?string $revisionError = null;

    public bool $showCancelModal = false;

    public string $cancelReason = '';

    public ?string $cancelError = null;

    public function mount(Letter|int $letter): void
    {
        $id = $letter instanceof Letter ? $letter->id : $letter;
        $this->letter = Letter::with(['activeNumber', 'numbers', 'revisions.actor', 'revisions.oldNumber', 'revisions.newNumber', 'activities.actor', 'template', 'author'])
            ->findOrFail($id);

        $this->revisionPerihal = $this->letter->perihal;
        $this->revisionVariables = $this->letter->field_values ?? [];

        if ($this->letter->is_manual_number) {
            $this->activeRightTab = 'preview';
        }
    }

    public function transition(string $target, TransitionLetterStatusAction $action): void
    {
        try {
            $this->letter = $action->execute($this->letter, auth()->user(), $target);
            $this->dispatch('toast', ['type' => 'success', 'title' => 'Status surat diperbarui']);
        } catch (DomainException $e) {
            $this->dispatch('toast', ['type' => 'error', 'title' => 'Transisi gagal', 'description' => $e->getMessage()]);
        }
    }

    public function submitRevision(ReviseLetterAction $action): void
    {
        $this->revisionError = null;

        try {
            $result = $action->execute($this->letter, auth()->user(), [
                'use_new_number' => $this->useNewNumber,
                'revision_reason' => $this->revisionReason,
                'perihal' => $this->revisionPerihal,
                'variables' => $this->revisionVariables,
            ]);

            $this->letter = $result['letter']->load(['activeNumber', 'numbers', 'revisions.actor', 'revisions.oldNumber', 'revisions.newNumber', 'activities.actor', 'template']);
            $this->showReviseModal = false;
            $this->revisionReason = '';

            $this->dispatch('toast', [
                'type' => 'success',
                'title' => 'Revisi surat tercatat',
                'description' => "Nomor aktif: {$result['new_number']}",
            ]);
        } catch (DomainException $e) {
            $this->revisionError = $e->getMessage();
        }
    }

    public function submitCancel(CancelLetterAction $action): void
    {
        $this->cancelError = null;

        try {
            $this->letter = $action->execute($this->letter, auth()->user(), $this->cancelReason)
                ->load(['activeNumber', 'numbers', 'revisions.actor', 'activities.actor', 'template']);
            $this->showCancelModal = false;
            $this->cancelReason = '';

            $this->dispatch('toast', [
                'type' => 'success',
                'title' => 'Surat dibatalkan & nomor dihanguskan',
            ]);
        } catch (DomainException $e) {
            $this->cancelError = $e->getMessage();
        }
    }

    public function duplicate(DuplicateLetterAction $action): void
    {
        try {
            $copy = $action->execute($this->letter, auth()->user());
            $this->redirect(route('letters.show', $copy->id), navigate: false);
        } catch (DomainException $e) {
            $this->dispatch('toast', ['type' => 'error', 'title' => 'Gagal duplikasi', 'description' => $e->getMessage()]);
        }
    }

    public function refreshPreview(): void
    {
        $this->letter->refresh();
        $this->dispatch('toast', ['type' => 'success', 'title' => 'Pratinjau diperbarui']);
    }

    public function render(VariableTypeResolver $resolver, OnlyOfficeService $onlyOfficeService)
    {
        $workspace = app(CurrentWorkspace::class)->get();
        $role = $workspace ? $workspace->roleOf(auth()->user()) : null;
        $editableFields = $this->letter->template
            ? $resolver->editableFields($this->letter->template->variables_schema ?? [])
            : [];

        $onlyOfficeConfig = null;
        if (! $this->letter->is_manual_number) {
            try {
                $onlyOfficeConfig = $onlyOfficeService->buildEditorConfig($this->letter, auth()->user());
            } catch (\Throwable) {
                $onlyOfficeConfig = null;
            }
        }

        return view('livewire.letters.letter-show', [
            'workspace' => $workspace,
            'role' => $role,
            'editableFields' => $editableFields,
            'onlyOfficeConfig' => $onlyOfficeConfig,
        ])->layout('components.layouts.app', ['fullWidth' => true]);
    }
}
