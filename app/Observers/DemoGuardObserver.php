<?php

namespace App\Observers;

use App\Exceptions\DemoModeRestrictedException;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class DemoGuardObserver
{
    public function saving(Model $model): void
    {
        $this->guard($model);
    }

    public function deleting(Model $model): void
    {
        $this->guard($model);
    }

    private function guard(Model $model): void
    {
        $user = auth()->user();

        if ($user && $user->is_demo) {
            // Allow silent technical updates on the demo user itself (workspace switching and remember tokens)
            if ($model instanceof User && $model->id === $user->id) {
                $dirty = array_keys($model->getDirty());
                $allowed = ['current_workspace_id', 'remember_token'];
                if (empty(array_diff($dirty, $allowed))) {
                    return;
                }
            }

            throw new DemoModeRestrictedException('Mode Demo (Hanya Lihat): Perubahan tidak disimpan.');
        }
    }
}
