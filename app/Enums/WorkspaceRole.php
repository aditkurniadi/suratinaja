<?php

namespace App\Enums;

enum WorkspaceRole: string
{
    case Admin = 'admin';
    case Secretary = 'secretary';
    case Chairman = 'chairman';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Secretary => 'Sekretaris',
            self::Chairman => 'Ketua',
        };
    }

    /** Roles allowed to create/update letters, templates, bulk jobs. */
    public function canWrite(): bool
    {
        return $this !== self::Chairman;
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
