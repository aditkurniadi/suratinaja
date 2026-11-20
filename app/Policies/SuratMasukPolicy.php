<?php

namespace App\Policies;

use App\Models\SuratMasuk;
use App\Models\User;
use App\Support\CurrentWorkspace;

class SuratMasukPolicy
{
    private function canWriteFallback(User $user): bool
    {
        $ws = app(CurrentWorkspace::class)->get();
        if (! $ws) {
            return false;
        }

        return $ws->roleOf($user)?->canWrite() ?? false;
    }

    private function hasMemberFallback(User $user): bool
    {
        $ws = app(CurrentWorkspace::class)->get();
        if (! $ws) {
            return false;
        }

        return $ws->hasMember($user);
    }

    public function viewAny(User $user): bool
    {
        return $user->can('surat_masuk.view') || $this->hasMemberFallback($user);
    }

    public function view(User $user, SuratMasuk $suratMasuk): bool
    {
        return $user->can('surat_masuk.view') || $this->hasMemberFallback($user);
    }

    public function create(User $user): bool
    {
        return $user->can('surat_masuk.create') || $this->canWriteFallback($user);
    }

    public function update(User $user, SuratMasuk $suratMasuk): bool
    {
        return $user->can('surat_masuk.edit') || $this->canWriteFallback($user);
    }

    public function delete(User $user, SuratMasuk $suratMasuk): bool
    {
        return $user->can('surat_masuk.delete') || $this->canWriteFallback($user);
    }

    public function archive(User $user, SuratMasuk $suratMasuk): bool
    {
        return $user->can('surat_masuk.archive') || $this->canWriteFallback($user);
    }
}

