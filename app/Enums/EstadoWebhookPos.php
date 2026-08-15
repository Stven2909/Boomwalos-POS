<?php

namespace App\Enums;

enum EstadoWebhookPos: string
{
    case PENDIENTE = 'PENDIENTE';
    case PROCESADO = 'PROCESADO';
    case RECONCILIADO = 'RECONCILIADO';

    public function label(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente (fuera de orden)',
            self::PROCESADO => 'Procesado',
            self::RECONCILIADO => 'Reconciliado',
        };
    }
}
