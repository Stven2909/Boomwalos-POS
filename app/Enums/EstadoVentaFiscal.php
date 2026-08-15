<?php

namespace App\Enums;

enum EstadoVentaFiscal: string
{
    case SINCRONIZADO = 'SINCRONIZADO';
    case NO = 'NO';
    case ENVIO_FALLIDO = 'ENVIO_FALLIDO';

    public function label(): string
    {
        return match ($this) {
            self::SINCRONIZADO => 'Sincronizada (API recibió)',
            self::NO => 'Sin DTE emitido',
            self::ENVIO_FALLIDO => 'Fallo en el envío',
        };
    }
}
