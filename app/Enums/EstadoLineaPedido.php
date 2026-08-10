<?php

namespace App\Enums;

enum EstadoLineaPedido: string
{
    case ACTIVA = 'ACTIVA';
    case CANCELADA = 'CANCELADA';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVA => 'Activa',
            self::CANCELADA => 'Cancelada',
        };
    }
}
