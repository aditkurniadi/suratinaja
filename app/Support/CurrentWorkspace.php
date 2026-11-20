<?php

namespace App\Support;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;

class CurrentWorkspace
{
    private ?Workspace $workspace = null;

    public function set(?Workspace $workspace): void
    {
        $this->workspace = $workspace;
        if ($workspace?->id) {
            setPermissionsTeamId($workspace->id);
            if (auth()->check()) {
                app(\App\Services\Authorization\WorkspacePermissionRegistrar::class)->ensureUserRoleSynced($workspace, auth()->user());
            }
        }
    }

    public function get(): ?Workspace
    {
        if ($this->workspace !== null) {
            return $this->workspace;
        }

        /** @var User|null $user */
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        $this->workspace = $user->activeWorkspace();
        if ($this->workspace?->id) {
            setPermissionsTeamId($this->workspace->id);
            app(\App\Services\Authorization\WorkspacePermissionRegistrar::class)->ensureUserRoleSynced($this->workspace, $user);
        }

        return $this->workspace;
    }

    public function id(): ?int
    {
        return $this->get()?->id;
    }

    public function role(?User $user = null): ?WorkspaceRole
    {
        $user ??= auth()->user();
        $ws = $this->get();
        if (! $user || ! $ws) {
            return null;
        }

        return $ws->roleOf($user);
    }

    public function clear(): void
    {
        $this->workspace = null;
    }
}
