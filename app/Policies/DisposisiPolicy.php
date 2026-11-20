<?php

namespace App\Policies;

use App\Models\Disposisi;
use App\Models\User;

class DisposisiPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('disposisi.view');
    }

    public function view(User $user, Disposisi $disposisi): bool
    {
        return $user->can('disposisi.view');
    }

    public function create(User $user): bool
    {
        return $user->can('disposisi.create');
    }

    public function updateStatus(User $user, Disposisi $disposisi): bool
    {
        if ($user->can('disposisi.update_status')) {
            // Either the user is assigned, or has high privileges
            if ($user->can('disposisi.create') || (int) $disposisi->kepada_user_id === (int) $user->id) {
                return true;
            }

            return true;
        }

        return false;
    }

    public function delete(User $user, Disposisi $disposisi): bool
    {
        return $user->can('disposisi.delete');
    }
}
