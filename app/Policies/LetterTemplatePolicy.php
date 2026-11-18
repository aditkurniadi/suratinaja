<?php

namespace App\Policies;

use App\Models\LetterTemplate;
use App\Models\User;
use App\Models\Workspace;

class LetterTemplatePolicy
{
    public function view(User $user, LetterTemplate $template, ?Workspace $workspace = null): bool
    {
        if ($template->isGlobal()) {
            return true;
        }
        $ws = $workspace ?? $template->workspace;

        return $ws !== null && $ws->hasMember($user);
    }

    public function create(User $user, Workspace $workspace): bool
    {
        return $workspace->roleOf($user)?->canWrite() === true;
    }

    /** Global templates cannot be modified via workspace flows (DEC-001, AC-03-03). */
    public function update(User $user, LetterTemplate $template): bool
    {
        if ($template->isGlobal()) {
            return false;
        }

        return $template->workspace !== null
            && $template->workspace->roleOf($user)?->canWrite() === true;
    }

    public function manageGlobal(User $user): bool
    {
        return $user->is_super_admin === true;
    }
}
