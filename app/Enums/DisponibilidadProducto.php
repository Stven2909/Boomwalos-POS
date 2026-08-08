<?php

namespace App\Enums;

enum DisponibilidadProducto: string
{
    case DISPONIBLE = 'DISPONIBLE';
    case AGOTADO = 'AGOTADO';
    case TEMPORALMENTE_NO_DISPONIBLE = 'TEMPORALMENTE_NO_DISPONIBLE';

    public function label(): string
    {
        return match ($this) {
            self::DISPONIBLE => 'Disponible',
            self::AGOTADO => 'Agotado',
            self::TEMPORALMENTE_NO_DISPONIBLE => 'Temporalmente no disponible',
        };
    }
}
