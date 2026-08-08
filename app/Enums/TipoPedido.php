<?php

namespace App\Enums;

enum TipoPedido: string
{
    case MESA = 'MESA';
    case PARA_LLEVAR = 'PARA_LLEVAR';

    public function label(): string
    {
        return match ($this) {
            self::MESA => 'Mesa',
            self::PARA_LLEVAR => 'Para llevar',
        };
    }
}
