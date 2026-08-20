<?php

namespace App\Enums;

enum TipoConexionImpresora: string
{
    case RED = 'RED';
    case USB = 'USB';
    case PDF = 'PDF';

    public function label(): string
    {
        return match ($this) {
            self::RED => 'Red (Ethernet / WiFi)',
            self::USB => 'USB Local',
            self::PDF => 'Simulador PDF / Virtual',
        };
    }
}
