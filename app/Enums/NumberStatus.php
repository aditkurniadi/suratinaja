<?php

namespace App\Enums;

enum NumberStatus: string
{
    case Aktif = 'aktif';
    case Hangus = 'hangus';

    public function label(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::Hangus => 'Hangus',
        };
    }

    public function icon(): string
    {
        return $this === self::Aktif ? 'hash' : 'circle-x';
    }

    public function dot(): string
    {
        return $this === self::Aktif ? 'bg-emerald-500' : 'bg-red-500';
    }
}
