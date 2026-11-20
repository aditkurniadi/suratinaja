<?php

namespace App\Actions\Workspaces;

use App\Exceptions\DomainException;
use App\Models\User;
use App\Models\Workspace;
use App\Support\CurrentWorkspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DeleteWorkspaceAction
{
    /**
     * Delete a workspace, all its physical files, Spatie roles, and gracefully migrate active users.
     */
    public function execute(Workspace $workspace, ?User $executedBy = null): void
    {
        // Must leave at least 1 workspace in the system
        if (Workspace::count() <= 1) {
            throw new \RuntimeException('Tidak dapat menghapus workspace. Sistem membutuhkan minimal 1 workspace aktif.');
        }

        $wsId = $workspace->id;

        DB::transaction(function () use ($workspace, $wsId) {
            // 1. Reassign or clear active workspace for users currently inside this workspace
            $users = User::where('current_workspace_id', $wsId)->get();
            foreach ($users as $user) {
                $otherWorkspace = $user->workspaces()->where('workspaces.id', '!=', $wsId)->first();
                $user->forceFill([
                    'current_workspace_id' => $otherWorkspace?->id,
                ])->save();
            }

            // 2. Clean Spatie roles & permissions
            try {
                Role::where('workspace_id', $wsId)->delete();
                $rolesTable = config('permission.table_names.model_has_roles', 'model_has_roles');
                $permsTable = config('permission.table_names.model_has_permissions', 'model_has_permissions');

                DB::table($rolesTable)->where('workspace_id', $wsId)->delete();
                DB::table($permsTable)->where('workspace_id', $wsId)->delete();
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            } catch (\Throwable) {}

            // 3. Delete workspace record (database foreign keys cascade all letters, members, templates, etc.)
            $workspace->delete();
        });

        // 4. Clean physical storage files
        try {
            $local = Storage::disk('local');
            $local->deleteDirectory("previews/{$wsId}");
            $local->deleteDirectory("generated/{$wsId}");
            $local->deleteDirectory("templates/{$wsId}");
            $local->deleteDirectory("surat-masuk/{$wsId}");
            $local->deleteDirectory("envelopes/{$wsId}");

            $public = Storage::disk('public');
            $public->deleteDirectory("kop/{$wsId}");
        } catch (\Throwable) {}

        // 5. Update CurrentWorkspace singleton if matching
        try {
            $singleton = app(CurrentWorkspace::class);
            if ($singleton->get()?->id === $wsId) {
                $user = $executedBy ?: auth()->user();
                $singleton->set($user?->activeWorkspace());
            }
        } catch (\Throwable) {}
    }
}
