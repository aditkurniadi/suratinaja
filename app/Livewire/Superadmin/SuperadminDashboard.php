<?php

namespace App\Livewire\Superadmin;

use App\Enums\WorkspaceRole;
use App\Models\Letter;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\CurrentWorkspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Super Admin Panel')]
class SuperadminDashboard extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    // Modal Create User
    public bool $showCreateModal = false;

    public string $newName = '';

    public string $newEmail = '';

    public string $newPassword = '';

    public ?int $newWorkspaceId = null;

    public string $newWorkspaceRole = 'member';

    public bool $newIsSuperAdmin = false;

    // Modal Reset Password
    public bool $showResetPasswordModal = false;

    public ?int $resetUserId = null;

    public string $resetUserName = '';

    public string $resetPassword = '';

    public function mount(): void
    {
        if (! auth()->user()?->is_super_admin) {
            abort(403, 'Akses terbatas untuk Super Admin.');
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->reset(['newName', 'newEmail', 'newPassword', 'newWorkspaceId', 'newIsSuperAdmin']);
        $this->newWorkspaceRole = WorkspaceRole::Member->value;
        $this->resetValidation();
        $this->showCreateModal = true;
    }

    public function createUser(): void
    {
        $validated = $this->validate([
            'newName' => ['required', 'string', 'max:120'],
            'newEmail' => ['required', 'string', 'lowercase', 'email', 'max:150', 'unique:users,email'],
            'newPassword' => ['required', 'string', 'min:8'],
            'newWorkspaceId' => ['nullable', 'exists:workspaces,id'],
            'newWorkspaceRole' => ['required', Rule::enum(WorkspaceRole::class)],
            'newIsSuperAdmin' => ['boolean'],
        ], [], [
            'newName' => 'Nama Lengkap',
            'newEmail' => 'Email',
            'newPassword' => 'Kata Sandi',
            'newWorkspaceId' => 'Workspace',
            'newWorkspaceRole' => 'Peran Workspace',
        ]);

        DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => trim($validated['newName']),
                'email' => trim($validated['newEmail']),
                'password' => Hash::make($validated['newPassword']),
                'email_verified_at' => now(),
                'status' => 'active',
                'is_demo' => false,
                'is_super_admin' => (bool) ($validated['newIsSuperAdmin'] ?? false),
            ]);

            if (! empty($validated['newWorkspaceId'])) {
                $ws = Workspace::find($validated['newWorkspaceId']);
                if ($ws) {
                    WorkspaceMember::create([
                        'workspace_id' => $ws->id,
                        'user_id' => $user->id,
                        'role' => $validated['newWorkspaceRole'],
                        'joined_at' => now(),
                    ]);

                    $user->forceFill(['current_workspace_id' => $ws->id])->saveQuietly();
                }
            }
        });

        $this->showCreateModal = false;
        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Pengguna Ditambahkan',
            'description' => "Pengguna {$this->newName} berhasil dibuat.",
        ]);
        $this->reset(['newName', 'newEmail', 'newPassword', 'newWorkspaceId', 'newIsSuperAdmin']);
    }

    public function toggleUserStatus(int $userId): void
    {
        $currentAdmin = auth()->user();
        if ($userId === $currentAdmin?->id) {
            $this->dispatch('toast', [
                'type' => 'error',
                'title' => 'Tindakan Ditolak',
                'description' => 'Anda tidak dapat menonaktifkan akun sendiri.',
            ]);
            return;
        }

        $user = User::findOrFail($userId);

        if ($user->email === config('suratinaja.super_admin.email')) {
            $this->dispatch('toast', [
                'type' => 'error',
                'title' => 'Tindakan Ditolak',
                'description' => 'Akun Root Super Admin sistem tidak dapat dinonaktifkan.',
            ]);
            return;
        }

        $newStatus = $user->status === 'suspended' ? 'active' : 'suspended';
        $user->forceFill(['status' => $newStatus])->save();

        $actionLabel = $newStatus === 'suspended' ? 'dinonaktifkan (suspended)' : 'diaktifkan kembali';
        $this->dispatch('toast', [
            'type' => $newStatus === 'suspended' ? 'warning' : 'success',
            'title' => 'Status Pengguna Diperbarui',
            'description' => "Akun {$user->name} berhasil {$actionLabel}.",
        ]);
    }

    public function openResetPasswordModal(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->resetUserId = $user->id;
        $this->resetUserName = $user->name;
        $this->resetPassword = '';
        $this->resetValidation();
        $this->showResetPasswordModal = true;
    }

    public function resetUserPassword(): void
    {
        $this->validate([
            'resetPassword' => ['required', 'string', 'min:8'],
        ], [], [
            'resetPassword' => 'Kata Sandi Baru',
        ]);

        if (! $this->resetUserId) {
            return;
        }

        $user = User::findOrFail($this->resetUserId);
        $user->forceFill([
            'password' => Hash::make($this->resetPassword),
        ])->save();

        $this->showResetPasswordModal = false;
        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Kata Sandi Diperbarui',
            'description' => "Kata sandi untuk {$user->name} berhasil diubah.",
        ]);
        $this->reset(['resetUserId', 'resetUserName', 'resetPassword']);
    }

    public function switchToWorkspace(int $workspaceId): void
    {
        $workspace = Workspace::findOrFail($workspaceId);
        $user = auth()->user();

        $user->forceFill(['current_workspace_id' => $workspace->id])->save();
        app(CurrentWorkspace::class)->set($workspace);

        $this->redirect(route('dashboard'), navigate: false);
    }

    public function render()
    {
        $hasStatus = \Illuminate\Support\Facades\Schema::hasColumn('users', 'status');
        $hasDemo = \Illuminate\Support\Facades\Schema::hasColumn('users', 'is_demo');

        $globalStats = [
            'totalUsers' => User::count(),
            'activeUsers' => $hasStatus ? User::where('status', 'active')->count() : User::count(),
            'suspendedUsers' => $hasStatus ? User::where('status', 'suspended')->count() : 0,
            'demoUsers' => $hasDemo ? User::where('is_demo', true)->count() : 0,
            'totalWorkspaces' => Workspace::count(),
            'totalLetters' => Letter::count(),
            'totalSuratMasuk' => SuratMasuk::count(),
        ];

        $activeWorkspaces = Workspace::withCount(['members', 'letters'])
            ->latest()
            ->take(6)
            ->get();

        $usersQuery = User::with(['workspaces'])
            ->when($this->search !== '', function ($q) {
                $term = '%'.trim($this->search).'%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->when($hasStatus && $this->statusFilter === 'active', fn ($q) => $q->where('status', 'active'))
            ->when($hasStatus && $this->statusFilter === 'suspended', fn ($q) => $q->where('status', 'suspended'))
            ->when($hasDemo && $this->statusFilter === 'demo', fn ($q) => $q->where('is_demo', true))
            ->orderBy('id', 'desc');

        $users = $usersQuery->paginate(10);
        $availableWorkspaces = Workspace::orderBy('name')->get(['id', 'name']);
        $roleOptions = WorkspaceRole::options();

        return view('livewire.superadmin.superadmin-dashboard', [
            'globalStats' => $globalStats,
            'activeWorkspaces' => $activeWorkspaces,
            'users' => $users,
            'availableWorkspaces' => $availableWorkspaces,
            'roleOptions' => $roleOptions,
        ]);
    }
}
