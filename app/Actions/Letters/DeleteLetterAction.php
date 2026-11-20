<?php

namespace App\Actions\Letters;

use App\Enums\LetterStatus;
use App\Exceptions\DomainException;
use App\Models\Letter;
use App\Models\LetterNumber;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Models\WorkspaceAuditLog;
use App\Services\Numbering\NumberAllocator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteLetterAction
{
    public function __construct(
        private readonly NumberAllocator $allocator,
    ) {}

    /**
     * Deletes an unsigned draft letter, deletes physical docx/pdf files, releases
     * its LetterNumber allocation, and rolls back the sequence counter if it was
     * the latest issued sequence in the period, ensuring the number is not burned.
     *
     * @throws DomainException
     */
    public function execute(Letter $letter, User $actor): bool
    {
        $workspace = $letter->workspace()->withoutGlobalScopes()->firstOrFail();

        $canDelete = $actor->is_super_admin
            || $actor->can('surat_keluar.delete')
            || ($workspace->roleOf($actor)?->canWrite() ?? false);

        if (! $canDelete) {
            throw DomainException::workspaceForbidden('Anda tidak memiliki izin menghapus surat pada workspace ini.');
        }

        if ($letter->signed_at !== null || $letter->status === LetterStatus::Selesai) {
            throw new DomainException(
                'CANNOT_DELETE_SIGNED_LETTER',
                'Surat yang sudah ditandatangani (Selesai) tidak dapat dihapus demi kepatuhan arsip hukum. Gunakan fitur Batalkan Surat.',
                422
            );
        }

        return DB::transaction(function () use ($letter, $workspace, $actor) {
            /** @var Letter $locked */
            $locked = Letter::withoutGlobalScopes()->lockForUpdate()->findOrFail($letter->id);

            if ($locked->signed_at !== null || $locked->status === LetterStatus::Selesai) {
                throw new DomainException(
                    'CANNOT_DELETE_SIGNED_LETTER',
                    'Surat yang sudah ditandatangani (Selesai) tidak dapat dihapus demi kepatuhan arsip hukum. Gunakan fitur Batalkan Surat.',
                    422
                );
            }

            $activeNumber = $locked->activeNumber()->first() ?? $locked->latestNumber()->first();
            $seq = $activeNumber?->sequence;
            $periodKey = $activeNumber?->period_key ?? $this->allocator->periodKey($locked->letter_date);
            $formattedNumber = $activeNumber?->formatted_number;

            // 1. Clean up physical files from disk if stored
            if ($locked->generated_file_path && Storage::disk('local')->exists($locked->generated_file_path)) {
                Storage::disk('local')->delete($locked->generated_file_path);
            }
            if ($locked->preview_pdf_path && Storage::disk('local')->exists($locked->preview_pdf_path)) {
                Storage::disk('local')->delete($locked->preview_pdf_path);
            }

            // 2. Unlink from Surat Masuk if linked
            SuratMasuk::where('surat_keluar_id', $locked->id)->update([
                'surat_keluar_id' => null,
            ]);

            // 3. Record in WorkspaceAuditLog before deleting
            WorkspaceAuditLog::create([
                'workspace_id' => $workspace->id,
                'user_id' => $actor->id,
                'action' => 'letter_deleted',
                'description' => "Draf surat '{$locked->perihal}'".($formattedNumber ? " ({$formattedNumber})" : '').' dihapus dan nomor surat dilepaskan.',
                'payload' => [
                    'letter_id' => $locked->id,
                    'perihal' => $locked->perihal,
                    'formatted_number' => $formattedNumber,
                    'sequence' => $seq,
                    'status_before' => $locked->status->value,
                    'period_key' => $periodKey,
                ],
                'created_at' => now(),
            ]);

            // 4. Delete the letter (cascades letter_numbers, letter_activities, letter_revisions)
            $locked->delete();

            // 5. Rollback sequence counter in letter_number_sequences if this was the latest sequence
            $maxRemaining = (int) LetterNumber::withoutGlobalScopes()
                ->where('workspace_id', $workspace->id)
                ->where('period_key', $periodKey)
                ->max('sequence');

            DB::table('letter_number_sequences')
                ->where('workspace_id', $workspace->id)
                ->where('period_key', $periodKey)
                ->update([
                    'last_sequence' => $maxRemaining,
                    'updated_at' => now(),
                ]);

            return true;
        });
    }
}
