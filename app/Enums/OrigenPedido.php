<?php

namespace App\Enums;

enum OrigenPedido: string
{
    case CAJA = 'CAJA';
    case DISPOSITIVO = 'DISPOSITIVO';

    public function label(): string
    {
        return match ($this) {
            self::CAJA => 'Caja',
            self::DISPOSITIVO => 'Dispositivo',
        };
    }
}
