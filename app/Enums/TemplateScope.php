<?php

namespace App\Enums;

enum TemplateScope: string
{
    case Global = 'global';
    case Workspace = 'workspace';

    public function label(): string
    {
        return $this === self::Global ? 'Global' : 'Workspace';
    }
}
