<?php

namespace App\Enums;

enum EstadoColaVentaFiscal: string
{
    case PENDIENTE = 'PENDIENTE';
    case ENVIADO = 'ENVIADO';
    case FALLIDO = 'FALLIDO';

    public function label(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente de envío',
            self::ENVIADO => 'Enviado',
            self::FALLIDO => 'Fallido',
        };
    }
}
