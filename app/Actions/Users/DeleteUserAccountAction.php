<?php

namespace App\Actions\Users;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class DeleteUserAccountAction
{
    /**
     * Delete user account, revoke workspace memberships, and logout.
     */
    public function execute(User $user): void
    {
        // 1. Prevent deleting the only Superadmin
        if ($user->is_super_admin && User::where('is_super_admin', true)->count() <= 1) {
            throw new \RuntimeException('Tidak dapat menghapus akun. Anda adalah satu-satunya Super Admin di sistem.');
        }

        // 2. Check if user is the sole admin in any workspace with other members
        $adminMemberships = WorkspaceMember::where('user_id', $user->id)
            ->where('role', WorkspaceRole::Admin)
            ->with('workspace')
            ->get();

        foreach ($adminMemberships as $membership) {
            $ws = $membership->workspace;
            if (! $ws) {
                continue;
            }

            $otherAdminsCount = WorkspaceMember::where('workspace_id', $ws->id)
                ->where('role', WorkspaceRole::Admin)
                ->where('user_id', '!=', $user->id)
                ->count();

            $totalMembersCount = WorkspaceMember::where('workspace_id', $ws->id)->count();

            if ($otherAdminsCount === 0 && $totalMembersCount > 1) {
                throw new \RuntimeException("Tidak dapat menghapus akun. Anda adalah satu-satunya Admin di workspace '{$ws->name}'. Silakan tetapkan anggota lain sebagai Admin terlebih dahulu.");
            }
        }

        // Logout and invalidate session first (so cycleRememberToken updates before delete)
        try {
            Auth::guard('web')->logout();
            if (request()->hasSession()) {
                request()->session()->invalidate();
                request()->session()->regenerateToken();
            }
        } catch (\Throwable) {}

        DB::transaction(function () use ($user) {
            // Remove all workspace memberships
            WorkspaceMember::where('user_id', $user->id)->delete();

            // Clean Spatie roles for user
            try {
                $rolesTable = config('permission.table_names.model_has_roles', 'model_has_roles');
                $permsTable = config('permission.table_names.model_has_permissions', 'model_has_permissions');

                DB::table($rolesTable)->where('model_id', $user->id)->delete();
                DB::table($permsTable)->where('model_id', $user->id)->delete();
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            } catch (\Throwable) {}

            // Delete user record
            $user->delete();
        });
    }
}
