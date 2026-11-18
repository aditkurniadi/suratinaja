<?php

namespace App\Enums;

enum BulkJobStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Done = 'done';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Antre',
            self::Processing => 'Diproses',
            self::Done => 'Selesai',
            self::Failed => 'Gagal',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Queued => 'clock',
            self::Processing => 'loader-circle',
            self::Done => 'circle-check',
            self::Failed => 'circle-x',
        };
    }

    public function dot(): string
    {
        return match ($this) {
            self::Queued => 'bg-zinc-400',
            self::Processing => 'bg-amber-500 animate-pulse',
            self::Done => 'bg-emerald-500',
            self::Failed => 'bg-red-500',
        };
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Done, self::Failed], true);
    }
}
