<?php

namespace App\Enums;

enum EstadoDocumentoFiscal: string
{
    case PENDIENTE = 'PENDIENTE';
    case EMITIDO = 'EMITIDO';
    case RECHAZADO = 'RECHAZADO';

    public function label(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente',
            self::EMITIDO => 'Emitido',
            self::RECHAZADO => 'Rechazado',
        };
    }
}
