<?php

namespace App\Enums;

enum AreaPreparacion: string
{
    case COCINA = 'COCINA';
    case BAR = 'BAR';
    case PUPUSAS = 'PUPUSAS';

    public function label(): string
    {
        return match ($this) {
            self::COCINA => 'Cocina',
            self::BAR => 'Bar',
            self::PUPUSAS => 'Pupusas',
        };
    }
}
