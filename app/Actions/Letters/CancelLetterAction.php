<?php

namespace App\Actions\Letters;

use App\Enums\LetterStatus;
use App\Enums\NumberStatus;
use App\Exceptions\DomainException;
use App\Models\Letter;
use App\Models\LetterActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CancelLetterAction
{
    /**
     * Cancels a letter (no hard delete): requires a reason, voids the active number
     * to `hangus`, and marks the letter as `dibatalkan` (terminal).
     *
     * @throws DomainException
     */
    public function execute(Letter $letter, User $actor, string $reason): Letter
    {
        $workspace = $letter->workspace()->withoutGlobalScopes()->firstOrFail();
        if (! $workspace->roleOf($actor)?->canWrite()) {
            throw DomainException::workspaceForbidden('Peran Ketua tidak diizinkan membatalkan surat.');
        }

        if ($letter->status === LetterStatus::Dibatalkan) {
            throw new DomainException('ALREADY_CANCELLED', 'Surat ini sudah berstatus dibatalkan.', 422);
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('CANCEL_REASON_REQUIRED', 'Alasan pembatalan surat wajib diisi.', 422);
        }

        return DB::transaction(function () use ($letter, $workspace, $actor, $reason) {
            /** @var Letter $locked */
            $locked = Letter::withoutGlobalScopes()->lockForUpdate()->findOrFail($letter->id);
            $activeNumber = $locked->activeNumber()->first();

            if ($activeNumber) {
                $activeNumber->forceFill([
                    'status' => NumberStatus::Hangus,
                    'revision_reason' => "Dibatalkan: {$reason}",
                    'voided_at' => now(),
                ])->save();
            }

            $locked->forceFill([
                'status' => LetterStatus::Dibatalkan,
                'cancel_reason' => $reason,
                'cancelled_at' => now(),
            ])->save();

            LetterActivity::withoutGlobalScopes()->create([
                'letter_id' => $locked->id,
                'workspace_id' => $workspace->id,
                'actor_id' => $actor->id,
                'action' => 'cancelled',
                'payload' => [
                    'reason' => $reason,
                    'voided_number' => $activeNumber?->formatted_number,
                ],
                'created_at' => now(),
            ]);

            return $locked->fresh(['numbers', 'activeNumber']);
        });
    }
}
