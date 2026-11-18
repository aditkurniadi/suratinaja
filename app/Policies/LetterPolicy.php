<?php

namespace App\Policies;

use App\Models\Letter;
use App\Models\User;
use App\Models\Workspace;

class LetterPolicy
{
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $workspace->hasMember($user);
    }

    public function view(User $user, Letter $letter): bool
    {
        return $letter->workspace && $letter->workspace->hasMember($user);
    }

    public function create(User $user, Workspace $workspace): bool
    {
        return $workspace->roleOf($user)?->canWrite() === true;
    }

    public function update(User $user, Letter $letter): bool
    {
        return $letter->workspace && $letter->workspace->roleOf($user)?->canWrite() === true;
    }
}
