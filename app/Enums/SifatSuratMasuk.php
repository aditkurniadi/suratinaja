<?php

namespace App\Enums;

enum SifatSuratMasuk: string
{
    case Biasa = 'biasa';
    case Penting = 'penting';
    case Segera = 'segera';

    public function label(): string
    {
        return match ($this) {
            self::Biasa => 'Biasa',
            self::Penting => 'Penting',
            self::Segera => 'Segera',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Segera => 'bg-red-500/10 text-red-700 dark:text-red-400 border border-red-500/20',
            self::Penting => 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20',
            self::Biasa => 'bg-muted text-muted-foreground border border-border',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all();
    }
}
