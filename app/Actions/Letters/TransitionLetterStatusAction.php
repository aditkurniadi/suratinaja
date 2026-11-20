<?php

namespace App\Actions\Letters;

use App\Enums\LetterStatus;
use App\Exceptions\DomainException;
use App\Models\Letter;
use App\Models\LetterActivity;
use App\Models\User;

class TransitionLetterStatusAction
{
    /**
     * Transitions a letter along DEC-004 state machine:
     * draft -> menunggu_ttd -> selesai (sets signed_at).
     *
     * @throws DomainException
     */
    public function execute(Letter $letter, User $actor, string|LetterStatus $target): Letter
    {
        $workspace = $letter->workspace()->withoutGlobalScopes()->firstOrFail();

        $targetStatus = $target instanceof LetterStatus
            ? $target
            : LetterStatus::tryFrom(strtolower(trim($target)));

        if (! $targetStatus) {
            throw DomainException::invalidTransition('Status tujuan tidak valid.');
        }

        $canAcc = $actor->can('surat_keluar.acc');
        $canWrite = $workspace->roleOf($actor)?->canWrite() ?? false;

        if ($targetStatus === LetterStatus::Selesai) {
            if (! $canAcc && ! $canWrite && ! $actor->is_super_admin) {
                throw DomainException::workspaceForbidden('Anda tidak memiliki izin menyetujui (ACC) surat.');
            }
        } else {
            if (! $canWrite && ! $actor->can('surat_keluar.edit') && ! $actor->is_super_admin) {
                throw DomainException::workspaceForbidden('Peran Anda tidak diizinkan mengubah status surat.');
            }
        }

        $currentStatus = $letter->status;
        if (! $currentStatus->canTransitionTo($targetStatus)) {
            throw DomainException::invalidTransition(
                "Transisi status dari '{$currentStatus->value}' ke '{$targetStatus->value}' tidak diizinkan."
            );
        }

        $updates = ['status' => $targetStatus];
        if ($targetStatus === LetterStatus::Selesai) {
            $updates['signed_at'] = now();
        }

        $letter->fill($updates)->save();

        LetterActivity::withoutGlobalScopes()->create([
            'letter_id' => $letter->id,
            'workspace_id' => $workspace->id,
            'actor_id' => $actor->id,
            'action' => 'status_changed',
            'payload' => [
                'from' => $currentStatus->value,
                'to' => $targetStatus->value,
            ],
            'created_at' => now(),
        ]);

        return $letter->fresh();
    }
}
