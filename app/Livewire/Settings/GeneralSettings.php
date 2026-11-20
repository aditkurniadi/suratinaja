<?php

namespace App\Livewire\Settings;

use App\Actions\Workspaces\DeleteWorkspaceAction;
use App\Models\Workspace;
use App\Services\Workspaces\WorkspaceStorageService;
use App\Support\CurrentWorkspace;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Pengaturan Workspace')]
class GeneralSettings extends Component
{
    public Workspace $workspace;

    public string $name = '';

    public string $number_code = '';

    public array $storageUsage = [];

    public bool $showDeleteModal = false;

    public string $confirmWorkspaceName = '';

    public ?string $errorMessage = null;

    public ?string $successMessage = null;

    protected array $rules = [
        'name' => ['required', 'string', 'min:2', 'max:120'],
        'number_code' => ['nullable', 'string', 'max:40'],
    ];

    public function mount(WorkspaceStorageService $storageService): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        if (! $ws) {
            abort(404, 'Workspace tidak ditemukan.');
        }

        $user = auth()->user();
        if (! $user || (! $user->is_super_admin && ! $ws->hasMember($user))) {
            abort(403, 'Anda tidak memiliki hak akses mengelola pengaturan workspace ini.');
        }

        $this->workspace = $ws;
        $this->name = $ws->name;
        $this->number_code = (string) ($ws->number_code ?? '');
        $this->refreshStorageUsage($storageService);
    }

    public function refreshStorageUsage(WorkspaceStorageService $storageService): void
    {
        $this->storageUsage = $storageService->calculateUsage($this->workspace);
    }

    public function saveIdentity(): void
    {
        $this->validate();
        $this->errorMessage = null;

        $this->workspace->update([
            'name' => trim($this->name),
            'number_code' => trim($this->number_code) ?: null,
        ]);

        $this->successMessage = 'Identitas workspace berhasil diperbarui.';
    }

    public function clearStorageCache(WorkspaceStorageService $storageService): void
    {
        $result = $storageService->clearTemporaryFiles($this->workspace);
        $this->refreshStorageUsage($storageService);

        $this->successMessage = "Berhasil membersihkan {$result['files_deleted']} berkas pratinjau sementara (menghemat {$result['freed_formatted']} ruang penyimpanan).";
    }

    public function openDeleteModal(): void
    {
        $this->confirmWorkspaceName = '';
        $this->errorMessage = null;
        $this->showDeleteModal = true;
    }

    public function deleteWorkspace(DeleteWorkspaceAction $deleteAction): void
    {
        $this->errorMessage = null;

        if (trim($this->confirmWorkspaceName) !== $this->workspace->name) {
            $this->errorMessage = 'Nama workspace yang diketik tidak cocok. Penghapusan dibatalkan demi keamanan.';
            return;
        }

        if (Workspace::count() <= 1) {
            $this->errorMessage = 'Tidak dapat menghapus workspace. Sistem membutuhkan minimal 1 workspace aktif.';
            return;
        }

        if (auth()->user()?->is_demo || $this->workspace->slug === 'demo-workspace') {
            $this->errorMessage = 'Workspace Demo dilindungi dan tidak dapat dihapus.';
            return;
        }

        try {
            $deleteAction->execute($this->workspace, auth()->user());
            session()->flash('status', "Workspace {$this->confirmWorkspaceName} berhasil dihapus permanen.");
            $this->redirect(route('dashboard'), navigate: false);
        } catch (\Throwable $e) {
            $this->errorMessage = 'Gagal menghapus workspace: ' . $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.settings.general-settings')
            ->layout('components.layouts.app', [
                'title' => 'Pengaturan Workspace',
                'breadcrumbs' => [
                    ['label' => 'Konfigurasi', 'url' => route('settings.kop')],
                    ['label' => 'Pengaturan Workspace'],
                ],
            ]);
    }
}
