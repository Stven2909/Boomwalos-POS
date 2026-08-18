<?php

namespace App\Enums;

enum TipoConexionImpresora: string
{
    case RED = 'RED';
    case USB = 'USB';

    public function label(): string
    {
        return match ($this) {
            self::RED => 'Red (Ethernet)',
            self::USB => 'USB',
        };
    }
}
