<?php

namespace App\Enums;

enum EstadoImpresion: string
{
    case PENDIENTE = 'PENDIENTE';
    case IMPRESO = 'IMPRESO';
    case ERROR = 'ERROR';

    public function label(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente',
            self::IMPRESO => 'Impreso',
            self::ERROR => 'Error',
        };
    }
}
