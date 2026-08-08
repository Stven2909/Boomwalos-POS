<?php

namespace App\Enums;

enum EstadoComercialPedido: string
{
    case ABIERTO = 'ABIERTO';
    case COBRADO = 'COBRADO';
    case CERRADO = 'CERRADO';

    public function label(): string
    {
        return match ($this) {
            self::ABIERTO => 'Abierto',
            self::COBRADO => 'Cobrado',
            self::CERRADO => 'Cerrado',
        };
    }
}
