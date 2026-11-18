<?php

namespace App\Services\Numbering;

use App\Enums\NumberStatus;
use App\Exceptions\DomainException;
use App\Models\Letter;
use App\Models\LetterNumber;
use App\Models\Workspace;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class NumberAllocator
{
    public function __construct(
        private readonly NumberPatternRenderer $renderer,
    ) {}

    public function periodKey(CarbonInterface $date): string
    {
        $reset = config('suratinaja.numbering.reset', 'never');

        return match ($reset) {
            'yearly' => $date->format('Y'),
            'monthly' => $date->format('Y-m'),
            default => 'all',
        };
    }

    /**
     * Atomically allocates the next formatted number and inserts a LetterNumber row.
     * Uses SELECT ... FOR UPDATE on letter_number_sequences inside a DB transaction.
     *
     * @throws DomainException
     */
    public function allocateForLetter(
        Letter $letter,
        Workspace $workspace,
        CarbonInterface $date,
        ?int $revisionOfId = null,
        ?string $revisionReason = null,
    ): LetterNumber {
        $attempts = max(1, (int) config('suratinaja.numbering.retry_attempts', 3));
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return DB::transaction(function () use ($letter, $workspace, $date, $revisionOfId, $revisionReason) {
                    $periodKey = $this->periodKey($date);

                    DB::table('letter_number_sequences')->insertOrIgnore([
                        'workspace_id' => $workspace->id,
                        'period_key' => $periodKey,
                        'last_sequence' => 0,
                        'updated_at' => now(),
                    ]);

                    $seqRow = DB::table('letter_number_sequences')
                        ->where('workspace_id', $workspace->id)
                        ->where('period_key', $periodKey)
                        ->lockForUpdate()
                        ->first();

                    $nextSeq = ((int) $seqRow->last_sequence) + 1;

                    DB::table('letter_number_sequences')
                        ->where('id', $seqRow->id)
                        ->update([
                            'last_sequence' => $nextSeq,
                            'updated_at' => now(),
                        ]);

                    $formatted = $this->renderer->render(
                        $workspace->number_pattern,
                        $workspace,
                        $nextSeq,
                        $date,
                    );

                    return LetterNumber::withoutGlobalScopes()->create([
                        'letter_id' => $letter->id,
                        'workspace_id' => $workspace->id,
                        'formatted_number' => $formatted,
                        'sequence' => $nextSeq,
                        'period_key' => $periodKey,
                        'status' => NumberStatus::Aktif,
                        'revision_reason' => $revisionReason,
                        'revision_of' => $revisionOfId,
                        'issued_at' => now(),
                    ]);
                });
            } catch (QueryException $e) {
                $lastException = $e;
                if ($this->isUniqueConstraintViolation($e) && $attempt < $attempts) {
                    usleep(25000 * $attempt);
                    continue;
                }
                if ($this->isUniqueConstraintViolation($e)) {
                    throw DomainException::numberConflict();
                }
                throw $e;
            }
        }

        throw DomainException::numberConflict($lastException?->getMessage() ?? 'Gagal mengalokasikan nomor surat.');
    }

    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? '';
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        return $sqlState === '23000' || $driverCode === 1062 || str_contains(strtolower($e->getMessage()), 'unique');
    }
}
