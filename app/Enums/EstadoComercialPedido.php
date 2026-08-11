<?php

namespace App\Enums;

enum EstadoComercialPedido: string
{
    case ABIERTO = 'ABIERTO';
    case PENDIENTE_COBRO = 'PENDIENTE_COBRO';
    case COBRADO = 'COBRADO';
    case CERRADO = 'CERRADO';

    public function label(): string
    {
        return match ($this) {
            self::ABIERTO => 'Abierto',
            self::PENDIENTE_COBRO => 'Pendiente de cobro',
            self::COBRADO => 'Cobrado',
            self::CERRADO => 'Cerrado',
        };
    }

    public function isPayable(): bool
    {
        return in_array($this, [self::ABIERTO, self::PENDIENTE_COBRO], true);
    }

    public function isReadOnly(): bool
    {
        return in_array($this, [self::COBRADO, self::CERRADO], true);
    }
}
