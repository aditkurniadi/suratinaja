<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    public function view(User $user, Workspace $workspace): bool
    {
        return $workspace->hasMember($user);
    }

    public function manage(User $user, Workspace $workspace): bool
    {
        return $workspace->roleOf($user)?->isAdmin() === true;
    }

    public function write(User $user, Workspace $workspace): bool
    {
        return $workspace->roleOf($user)?->canWrite() === true;
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        return (bool) $user->is_super_admin || $workspace->roleOf($user)?->isAdmin() === true;
    }
}
