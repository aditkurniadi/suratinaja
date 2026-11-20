<?php

namespace App\Enums;

enum WorkspaceRole: string
{
    case Admin = 'admin';
    case Secretary = 'secretary';
    case Chairman = 'chairman';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Secretary => 'Sekretaris',
            self::Chairman => 'Ketua',
            self::Member => 'Anggota',
        };
    }

    /** Roles allowed to create/update letters, templates, bulk jobs. */
    public function canWrite(): bool
    {
        return $this === self::Admin || $this === self::Secretary;
    }

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($r) => [$r->value => $r->label()])->all();
    }
}
