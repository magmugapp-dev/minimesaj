<?php

namespace App\Exceptions;

use RuntimeException;

class MesajlasmaEngeliException extends RuntimeException
{
    public function __construct(string $message = 'Bu kullanici sizi engelledi.')
    {
        parent::__construct($message);
    }
}
