<?php

namespace App\Enums;

enum EstadoImpresion: string
{
    case PENDIENTE = 'PENDIENTE';
    case PROCESANDO = 'PROCESANDO';
    case IMPRESO = 'IMPRESO';
    case ERROR = 'ERROR';

    public function label(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente',
            self::PROCESANDO => 'Procesando',
            self::IMPRESO => 'Impreso',
            self::ERROR => 'Error',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDIENTE => 'gray',
            self::PROCESANDO => 'info',
            self::IMPRESO => 'success',
            self::ERROR => 'danger',
        };
    }
}
