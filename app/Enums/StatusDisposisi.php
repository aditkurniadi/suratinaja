<?php

namespace App\Enums;

enum StatusDisposisi: string
{
    case BelumDiproses = 'belum_diproses';
    case SedangDiproses = 'sedang_diproses';
    case Selesai = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::BelumDiproses => 'Belum Diproses',
            self::SedangDiproses => 'Sedang Diproses',
            self::Selesai => 'Selesai',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::BelumDiproses => 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20',
            self::SedangDiproses => 'bg-blue-500/10 text-blue-700 dark:text-blue-400 border border-blue-500/20',
            self::Selesai => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all();
    }
}
