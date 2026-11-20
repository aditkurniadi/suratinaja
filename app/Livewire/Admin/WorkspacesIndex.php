<?php

namespace App\Livewire\Admin;

use App\Actions\Workspaces\DeleteWorkspaceAction;
use App\Models\Workspace;
use App\Services\Workspaces\WorkspaceStorageService;
use App\Support\CurrentWorkspace;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Manajemen Seluruh Workspace')]
class WorkspacesIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showDeleteModal = false;

    public ?int $selectedWorkspaceId = null;

    public string $selectedWorkspaceName = '';

    public string $confirmWorkspaceName = '';

    public ?string $errorMessage = null;

    public ?string $successMessage = null;

    public function mount(): void
    {
        if (! auth()->user()?->is_super_admin) {
            abort(403, 'Hanya Super Admin yang dapat mengakses halaman manajemen seluruh workspace.');
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function switchToWorkspace(int $workspaceId): void
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $user = auth()->user();

        $user->forceFill(['current_workspace_id' => $workspace->id])->save();
        app(CurrentWorkspace::class)->set($workspace);

        $this->redirect(route('dashboard'), navigate: false);
    }

    public function confirmDelete(int $workspaceId): void
    {
        $ws = Workspace::findOrFail($workspaceId);
        $this->selectedWorkspaceId = $ws->id;
        $this->selectedWorkspaceName = $ws->name;
        $this->confirmWorkspaceName = '';
        $this->errorMessage = null;
        $this->showDeleteModal = true;
    }

    public function deleteWorkspace(DeleteWorkspaceAction $action): void
    {
        $this->errorMessage = null;

        if (! $this->selectedWorkspaceId) {
            return;
        }

        $workspace = Workspace::find($this->selectedWorkspaceId);
        if (! $workspace) {
            $this->showDeleteModal = false;
            return;
        }

        if (trim($this->confirmWorkspaceName) !== $workspace->name) {
            $this->errorMessage = 'Nama workspace yang diketik tidak cocok. Penghapusan dibatalkan demi keamanan.';
            return;
        }

        if (Workspace::count() <= 1) {
            $this->errorMessage = 'Tidak dapat menghapus workspace. Sistem membutuhkan minimal 1 workspace aktif.';
            return;
        }

        try {
            $deletedName = $workspace->name;
            $action->execute($workspace, auth()->user());
            $this->showDeleteModal = false;
            $this->selectedWorkspaceId = null;
            $this->successMessage = "Workspace {$deletedName} berhasil dihapus permanen beserta seluruh berkasnya.";
        } catch (\Throwable $e) {
            $this->errorMessage = 'Gagal menghapus workspace: ' . $e->getMessage();
        }
    }

    public function render(WorkspaceStorageService $storageService)
    {
        $query = Workspace::query()
            ->withCount(['members', 'letters', 'suratMasuks'])
            ->orderBy('created_at', 'desc');

        if (filled($this->search)) {
            $term = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('slug', 'like', $term)
                    ->orWhere('number_code', 'like', $term);
            });
        }

        $workspaces = $query->paginate(10);

        return view('livewire.admin.workspaces-index', [
            'workspaces' => $workspaces,
            'totalWorkspaces' => Workspace::count(),
        ])->layout('components.layouts.app', [
            'title' => 'Manajemen Seluruh Workspace',
            'breadcrumbs' => [
                ['label' => 'Admin', 'url' => route('admin.global-templates')],
                ['label' => 'Semua Workspace'],
            ],
        ]);
    }
}
