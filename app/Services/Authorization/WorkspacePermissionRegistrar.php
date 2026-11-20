<?php

namespace App\Services\Authorization;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class WorkspacePermissionRegistrar
{
    public const ROLE_ADMIN = 'Admin Workspace';
    public const ROLE_SECRETARY = 'Sekretaris';
    public const ROLE_CHAIRMAN = 'Ketua / Pimpinan';
    public const ROLE_MEMBER = 'Pengurus / Anggota';
    public const ROLE_VIEWER = 'Viewer / Humas (Pratinjau & Unduh)';

    /**
     * Grouped permissions for UI matrix and seeder.
     *
     * @return array<string, array{label: string, permissions: array<string, string>}>
     */
    public static function permissionGroups(): array
    {
        return [
            'surat_keluar' => [
                'label' => 'Surat Keluar',
                'permissions' => [
                    'surat_keluar.view' => 'Melihat daftar dan pratinjau surat keluar',
                    'surat_keluar.preview_download' => 'Pratinjau & Unduh PDF Surat Keluar',
                    'surat_keluar.create' => 'Membuat draft surat keluar baru',
                    'surat_keluar.edit' => 'Mengubah draft surat keluar',
                    'surat_keluar.delete' => 'Menghapus draft / membatalkan surat keluar',
                    'surat_keluar.acc' => 'Menyetujui (ACC / Tandai Selesai) surat keluar',
                ],
            ],
            'surat_masuk' => [
                'label' => 'Surat Masuk',
                'permissions' => [
                    'surat_masuk.view' => 'Melihat daftar dan detail surat masuk',
                    'surat_masuk.preview_download' => 'Pratinjau & Unduh Dokumen Surat Masuk',
                    'surat_masuk.create' => 'Mencatat surat masuk & upload/link dokumen',
                    'surat_masuk.edit' => 'Mengubah metadata & dokumen surat masuk',
                    'surat_masuk.delete' => 'Menghapus arsip surat masuk',
                    'surat_masuk.archive' => 'Mengarsipkan surat masuk',
                ],
            ],
            'amplop' => [
                'label' => 'Kop Amplop',
                'permissions' => [
                    'amplop.preview_download' => 'Pratinjau & Unduh Kop Amplop',
                ],
            ],
            'bulk' => [
                'label' => 'Surat Massal / Bulk',
                'permissions' => [
                    'bulk.preview_download' => 'Pratinjau & Unduh Bulk Export 1 PDF & Semua Amplop',
                ],
            ],
            'disposisi' => [
                'label' => 'Disposisi & Tindak Lanjut',
                'permissions' => [
                    'disposisi.view' => 'Melihat riwayat & kartu disposisi',
                    'disposisi.create' => 'Memberi arahan disposisi (Pimpinan / Admin)',
                    'disposisi.update_status' => 'Memperbarui status tindak lanjut & mengisi catatan laporan',
                    'disposisi.delete' => 'Menghapus catatan disposisi',
                ],
            ],
            'template' => [
                'label' => 'Template Dokumen',
                'permissions' => [
                    'template.view' => 'Melihat katalog & mengunduh template',
                    'template.manage' => 'Mengunggah, mengedit di OnlyOffice, menyinkronkan variabel',
                ],
            ],
            'kop_surat' => [
                'label' => 'Kop Surat',
                'permissions' => [
                    'kop_surat.manage' => 'Mengatur kop surat, logo, dan penandatangan default',
                ],
            ],
            'penomoran' => [
                'label' => 'Pola Penomoran & Jenis Surat',
                'permissions' => [
                    'penomoran.manage' => 'Mengatur pola penomoran, kode kegiatan, jenis surat',
                ],
            ],
            'member' => [
                'label' => 'Manajemen Anggota',
                'permissions' => [
                    'member.view' => 'Melihat daftar anggota workspace',
                    'member.invite' => 'Mengundang anggota baru',
                    'member.assign_role' => 'Mengubah peran/role anggota',
                    'member.remove' => 'Mengeluarkan anggota dari workspace',
                ],
            ],
            'role' => [
                'label' => 'Role & Hak Akses',
                'permissions' => [
                    'role.view' => 'Melihat daftar role & matriks permission',
                    'role.manage' => 'Membuat, mengubah, dan menghapus role kustom serta permission',
                ],
            ],
        ];
    }

    /**
     * All flat permission keys.
     *
     * @return array<int, string>
     */
    public static function allPermissionKeys(): array
    {
        $keys = [];
        foreach (self::permissionGroups() as $group) {
            foreach (array_keys($group['permissions']) as $key) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * Ensures all permissions exist in database.
     */
    public function syncGlobalPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::allPermissionKeys() as $permName) {
            Permission::findOrCreate($permName, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Initializes default roles with permissions for a workspace.
     */
    public function initWorkspaceRoles(Workspace $workspace): void
    {
        $this->syncGlobalPermissions();
        setPermissionsTeamId($workspace->id);

        $allPerms = self::allPermissionKeys();

        // 1. Admin Workspace -> all permissions
        $adminRole = Role::findOrCreate(self::ROLE_ADMIN, 'web');
        $adminRole->syncPermissions($allPerms);

        // 2. Sekretaris -> full operational on letters, incoming, disposition, templates, numbering
        $secPerms = [
            'surat_keluar.view',
            'surat_keluar.preview_download',
            'surat_keluar.create',
            'surat_keluar.edit',
            'surat_keluar.delete',
            'surat_masuk.view',
            'surat_masuk.preview_download',
            'surat_masuk.create',
            'surat_masuk.edit',
            'surat_masuk.delete',
            'surat_masuk.archive',
            'amplop.preview_download',
            'bulk.preview_download',
            'disposisi.view',
            'disposisi.update_status',
            'template.view',
            'template.manage',
            'penomoran.manage',
            'member.view',
        ];
        $secRole = Role::findOrCreate(self::ROLE_SECRETARY, 'web');
        $secRole->syncPermissions($secPerms);

        // 3. Ketua / Pimpinan -> view letters, ACC letters, view incoming, full disposition, view templates, view members
        $chairPerms = [
            'surat_keluar.view',
            'surat_keluar.preview_download',
            'surat_keluar.acc',
            'surat_masuk.view',
            'surat_masuk.preview_download',
            'amplop.preview_download',
            'bulk.preview_download',
            'disposisi.view',
            'disposisi.create',
            'disposisi.update_status',
            'disposisi.delete',
            'template.view',
            'member.view',
        ];
        $chairRole = Role::findOrCreate(self::ROLE_CHAIRMAN, 'web');
        $chairRole->syncPermissions($chairPerms);

        // 4. Pengurus / Anggota -> view letters, view incoming, view & update assigned disposition
        $memberPerms = [
            'surat_keluar.view',
            'surat_keluar.preview_download',
            'surat_masuk.view',
            'surat_masuk.preview_download',
            'disposisi.view',
            'disposisi.update_status',
            'template.view',
        ];
        $memberRole = Role::findOrCreate(self::ROLE_MEMBER, 'web');
        $memberRole->syncPermissions($memberPerms);

        // 5. Viewer / Humas (Pratinjau & Unduh) -> can only view lists and preview/download documents
        $viewerPerms = [
            'surat_keluar.view',
            'surat_keluar.preview_download',
            'surat_masuk.view',
            'surat_masuk.preview_download',
            'amplop.preview_download',
            'bulk.preview_download',
            'template.view',
        ];
        $viewerRole = Role::findOrCreate(self::ROLE_VIEWER, 'web');
        $viewerRole->syncPermissions($viewerPerms);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @var array<string, bool> */
    protected static array $syncedCache = [];

    /**
     * Ensures a user has their Spatie role synced for the specified workspace.
     */
    public function ensureUserRoleSynced(Workspace $workspace, User $user): void
    {
        $cacheKey = $workspace->id . ':' . $user->id;
        if (isset(self::$syncedCache[$cacheKey])) {
            setPermissionsTeamId($workspace->id);
            return;
        }

        setPermissionsTeamId($workspace->id);

        $hasRoles = $user->roles()->where('roles.workspace_id', $workspace->id)->exists();
        if ($hasRoles) {
            self::$syncedCache[$cacheKey] = true;
            return;
        }

        // Ensure default roles exist for this workspace
        $count = Role::where('workspace_id', $workspace->id)->count();
        if ($count === 0) {
            $this->initWorkspaceRoles($workspace);
        }

        $member = WorkspaceMember::where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $member) {
            return;
        }

        $roleName = match ($member->role) {
            WorkspaceRole::Admin => self::ROLE_ADMIN,
            WorkspaceRole::Chairman => self::ROLE_CHAIRMAN,
            default => self::ROLE_SECRETARY,
        };

        $role = Role::where('workspace_id', $workspace->id)->where('name', $roleName)->first();
        if ($role) {
            $user->syncRoles([$role]);
            $user->unsetRelation('roles');
            $user->unsetRelation('permissions');
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    /**
     * Migrates legacy workspace_members.role into Spatie roles per workspace.
     */
    public function migrateExistingMembers(): void
    {
        $this->syncGlobalPermissions();

        $workspaces = Workspace::all();
        foreach ($workspaces as $workspace) {
            $this->initWorkspaceRoles($workspace);
            setPermissionsTeamId($workspace->id);

            $members = WorkspaceMember::where('workspace_id', $workspace->id)->get();
            foreach ($members as $member) {
                /** @var User|null $user */
                $user = User::find($member->user_id);
                if (! $user) {
                    continue;
                }

                // If user already has any Spatie role in this workspace, do not overwrite it
                $hasRoleInWorkspace = $user->roles()->where('roles.workspace_id', $workspace->id)->exists();
                if ($hasRoleInWorkspace) {
                    continue;
                }

                $roleName = match ($member->role) {
                    WorkspaceRole::Admin => self::ROLE_ADMIN,
                    WorkspaceRole::Chairman => self::ROLE_CHAIRMAN,
                    default => self::ROLE_SECRETARY,
                };

                $role = Role::where('workspace_id', $workspace->id)->where('name', $roleName)->first();
                if ($role) {
                    $user->syncRoles([$role]);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
