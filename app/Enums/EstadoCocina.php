<?php

namespace App\Enums;

enum EstadoCocina: string
{
    case PENDIENTE = 'PENDIENTE';
    case EN_PREPARACION = 'EN_PREPARACION';
    case LISTA = 'LISTA';
    case ENTREGADA = 'ENTREGADA';
    case CANCELADA = 'CANCELADA';

    public function label(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente',
            self::EN_PREPARACION => 'En preparación',
            self::LISTA => 'Lista',
            self::ENTREGADA => 'Entregada',
            self::CANCELADA => 'Cancelada',
        };
    }
}
