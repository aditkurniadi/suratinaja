<?php

namespace App\Livewire\Letters;

use App\Actions\Letters\DuplicateLetterAction;
use App\Actions\Letters\TransitionLetterStatusAction;
use App\Exceptions\DomainException;
use App\Models\Letter;
use App\Support\CurrentWorkspace;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Daftar Surat')]
class LettersIndex extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $bulk_job_id = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function transition(int $letterId, string $target, TransitionLetterStatusAction $action): void
    {
        $letter = Letter::findOrFail($letterId);

        try {
            $action->execute($letter, auth()->user(), $target);
            $this->dispatch('toast', [
                'type' => 'success',
                'title' => 'Status surat diperbarui',
            ]);
        } catch (DomainException $e) {
            $this->dispatch('toast', [
                'type' => 'error',
                'title' => 'Gagal memperbarui status',
                'description' => $e->getMessage(),
            ]);
        }
    }

    public function duplicate(int $letterId, DuplicateLetterAction $action): void
    {
        $letter = Letter::findOrFail($letterId);

        try {
            $copy = $action->execute($letter, auth()->user());
            $this->redirect(route('letters.show', $copy->id), navigate: false);
        } catch (DomainException $e) {
            $this->dispatch('toast', [
                'type' => 'error',
                'title' => 'Gagal menduplikasi surat',
                'description' => $e->getMessage(),
            ]);
        }
    }

    public function render()
    {
        $workspace = app(CurrentWorkspace::class)->get();
        $role = $workspace ? $workspace->roleOf(auth()->user()) : null;

        $query = Letter::with(['activeNumber', 'latestNumber', 'template', 'author', 'suratMasuk'])
            ->orderByDesc('id');

        if ($this->status !== '') {
            $query->where('status', strtolower($this->status));
        }

        if ($this->bulk_job_id !== '') {
            $query->where('bulk_job_id', $this->bulk_job_id);
        }

        $search = trim($this->search);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('perihal', 'like', "%{$search}%")
                    ->orWhereHas('numbers', fn ($nq) => $nq->where('formatted_number', 'like', "%{$search}%"));
            });
        }

        $letters = $query->paginate((int) config('suratinaja.letters.per_page', 15));

        return view('livewire.letters.letters-index', [
            'workspace' => $workspace,
            'role' => $role,
            'letters' => $letters,
        ])->layout('components.layouts.app', [
            'title' => 'Daftar Surat',
            'breadcrumbs' => [
                ['label' => 'Daftar Surat'],
            ],
        ]);
    }
}
