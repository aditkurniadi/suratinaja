<?php

namespace App\Exceptions;

use Exception;

class DemoModeRestrictedException extends Exception
{
    public function __construct(string $message = 'Mode Demo (Hanya Lihat): Perubahan tidak disimpan.')
    {
        parent::__construct($message);
    }
}
