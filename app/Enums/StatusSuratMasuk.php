<?php

namespace App\Enums;

enum StatusSuratMasuk: string
{
    case Baru = 'baru';
    case MenungguDisposisi = 'menunggu_disposisi';
    case Didisposisi = 'didisposisi';
    case Selesai = 'selesai';
    case Diarsipkan = 'diarsipkan';

    public function label(): string
    {
        return match ($this) {
            self::Baru => 'Baru',
            self::MenungguDisposisi => 'Menunggu Disposisi',
            self::Didisposisi => 'Didisposisi',
            self::Selesai => 'Selesai',
            self::Diarsipkan => 'Diarsipkan',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Baru => 'bg-blue-500/10 text-blue-700 dark:text-blue-400 border border-blue-500/20',
            self::MenungguDisposisi => 'bg-purple-500/10 text-purple-700 dark:text-purple-400 border border-purple-500/20',
            self::Didisposisi => 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20',
            self::Selesai => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20',
            self::Diarsipkan => 'bg-zinc-500/10 text-zinc-700 dark:text-zinc-400 border border-zinc-500/20',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all();
    }
}
