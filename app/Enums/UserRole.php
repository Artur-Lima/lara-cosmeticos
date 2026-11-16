<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Cliente = 'cliente';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Cliente => 'Cliente',
        };
    }
}
