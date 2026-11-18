<?php

namespace App\Enums;

/** Letter state machine (DEC-004) + cancellation. */
enum LetterStatus: string
{
    case Draft = 'draft';
    case MenungguTtd = 'menunggu_ttd';
    case Selesai = 'selesai';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::MenungguTtd => 'Menunggu TTD',
            self::Selesai => 'Selesai',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Draft => 'file-pen-line',
            self::MenungguTtd => 'clock',
            self::Selesai => 'circle-check',
            self::Dibatalkan => 'ban',
        };
    }

    public function dot(): string
    {
        return match ($this) {
            self::Draft => 'bg-zinc-400',
            self::MenungguTtd => 'bg-amber-500',
            self::Selesai => 'bg-emerald-500',
            self::Dibatalkan => 'bg-zinc-500',
        };
    }

    /** Manual transitions allowed via the status endpoint. */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::MenungguTtd],
            self::MenungguTtd => [self::Selesai],
            self::Selesai, self::Dibatalkan => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this === self::Dibatalkan;
    }

    /** Revision (DEC-005) applies only to letters that left Draft. */
    public function isRevisable(): bool
    {
        return in_array($this, [self::MenungguTtd, self::Selesai], true);
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all();
    }
}
