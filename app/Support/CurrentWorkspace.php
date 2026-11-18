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

        return $this->workspace = $user->activeWorkspace();
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
