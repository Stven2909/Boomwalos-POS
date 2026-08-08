<?php

namespace App\Enums;

enum TipoDocumento: string
{
    case FACTURA = 'FACTURA';
    case CCF = 'CCF';

    public function label(): string
    {
        return match ($this) {
            self::FACTURA => 'Factura',
            self::CCF => 'Comprobante de Crédito Fiscal',
        };
    }
}
