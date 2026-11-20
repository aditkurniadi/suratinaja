<?php

namespace App\Livewire\Settings;

use App\Services\Authorization\WorkspacePermissionRegistrar;
use App\Support\CurrentWorkspace;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

#[Title('Role & Hak Akses Workspace')]
class RolesManager extends Component
{
    public bool $showRoleModal = false;
    public ?int $editingRoleId = null;
    public string $roleName = '';
    public array $selectedPermissions = [];

    protected array $rules = [
        'roleName' => ['required', 'string', 'max:50'],
        'selectedPermissions' => ['array'],
    ];

    public function mount(): void
    {
        $ws = app(CurrentWorkspace::class)->get();
        if (! $ws || ! auth()->user()->can('role.view')) {
            abort(403, 'Anda tidak memiliki hak akses melihat konfigurasi role.');
        }
    }

    public function openCreateModal(): void
    {
        if (! auth()->user()->can('role.manage')) {
            abort(403);
        }

        $this->reset(['editingRoleId', 'roleName', 'selectedPermissions']);
        $this->showRoleModal = true;
    }

    public function openEditModal(int $roleId): void
    {
        if (! auth()->user()->can('role.manage')) {
            abort(403);
        }

        $ws = app(CurrentWorkspace::class)->get();
        setPermissionsTeamId($ws->id);

        $role = Role::where('workspace_id', $ws->id)->findOrFail($roleId);
        $this->editingRoleId = $role->id;
        $this->roleName = $role->name;
        $this->selectedPermissions = $role->permissions()->pluck('name')->all();
        $this->showRoleModal = true;
    }

    public function toggleGroup(string $groupKey): void
    {
        $groups = WorkspacePermissionRegistrar::permissionGroups();
        if (! isset($groups[$groupKey])) {
            return;
        }

        $groupPerms = array_keys($groups[$groupKey]['permissions']);
        $allSelected = count(array_intersect($groupPerms, $this->selectedPermissions)) === count($groupPerms);

        if ($allSelected) {
            $this->selectedPermissions = array_values(array_diff($this->selectedPermissions, $groupPerms));
        } else {
            $this->selectedPermissions = array_values(array_unique(array_merge($this->selectedPermissions, $groupPerms)));
        }
    }

    public function saveRole(): void
    {
        if (! auth()->user()->can('role.manage')) {
            abort(403);
        }

        $this->validate();
        $ws = app(CurrentWorkspace::class)->get();
        setPermissionsTeamId($ws->id);

        if ($this->editingRoleId) {
            $role = Role::where('workspace_id', $ws->id)->findOrFail($this->editingRoleId);
            // Default Admin role cannot be renamed
            if ($role->name !== WorkspacePermissionRegistrar::ROLE_ADMIN) {
                $role->update(['name' => trim($this->roleName)]);
            }
        } else {
            $role = Role::create([
                'name' => trim($this->roleName),
                'guard_name' => 'web',
                'workspace_id' => $ws->id,
            ]);
        }

        // Admin Workspace always retains all permissions
        if ($role->name === WorkspacePermissionRegistrar::ROLE_ADMIN) {
            $role->syncPermissions(WorkspacePermissionRegistrar::allPermissionKeys());
        } else {
            $role->syncPermissions($this->selectedPermissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->showRoleModal = false;
        $this->reset(['editingRoleId', 'roleName', 'selectedPermissions']);
        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Role berhasil disimpan',
            'description' => "Hak akses role {$role->name} telah diperbarui.",
        ]);
    }

    public function deleteRole(int $roleId): void
    {
        if (! auth()->user()->can('role.manage')) {
            abort(403);
        }

        $ws = app(CurrentWorkspace::class)->get();
        setPermissionsTeamId($ws->id);

        $role = Role::where('workspace_id', $ws->id)->findOrFail($roleId);

        $defaultRoles = [
            WorkspacePermissionRegistrar::ROLE_ADMIN,
            WorkspacePermissionRegistrar::ROLE_SECRETARY,
            WorkspacePermissionRegistrar::ROLE_CHAIRMAN,
            WorkspacePermissionRegistrar::ROLE_MEMBER,
        ];

        if (in_array($role->name, $defaultRoles, true)) {
            $this->dispatch('toast', [
                'type' => 'error',
                'title' => 'Aksi Ditolak',
                'description' => 'Role bawaan sistem tidak dapat dihapus.',
            ]);

            return;
        }

        if ($role->users()->count() > 0) {
            $this->dispatch('toast', [
                'type' => 'error',
                'title' => 'Role Sedang Digunakan',
                'description' => 'Role tidak dapat dihapus karena masih diberikan kepada anggota aktif.',
            ]);

            return;
        }

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->dispatch('toast', [
            'type' => 'success',
            'title' => 'Role dihapus',
            'description' => 'Role kustom berhasil dihapus dari workspace.',
        ]);
    }

    public function render()
    {
        $ws = app(CurrentWorkspace::class)->get();
        setPermissionsTeamId($ws->id);

        $roles = Role::where('workspace_id', $ws->id)
            ->withCount('permissions')
            ->withCount('users')
            ->orderBy('id')
            ->get();

        $groups = WorkspacePermissionRegistrar::permissionGroups();

        return view('livewire.settings.roles-manager', [
            'workspace' => $ws,
            'roles' => $roles,
            'permissionGroups' => $groups,
        ])->layout('components.layouts.app', [
            'title' => 'Peran & Hak Akses',
            'breadcrumbs' => [
                ['label' => 'Konfigurasi', 'url' => route('settings.numbering')],
                ['label' => 'Anggota & Akses', 'url' => route('settings.members')],
                ['label' => 'Peran & Hak Akses'],
            ],
            'backUrl' => route('settings.members'),
        ]);
    }
}
