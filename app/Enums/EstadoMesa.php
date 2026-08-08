<?php

namespace App\Enums;

enum EstadoMesa: string
{
    case LIBRE = 'LIBRE';
    case OCUPADA = 'OCUPADA';

    public function label(): string
    {
        return match ($this) {
            self::LIBRE => 'Libre',
            self::OCUPADA => 'Ocupada',
        };
    }
}
