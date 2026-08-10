<?php

namespace App\Enums;

enum ZonaMesa: string
{
    case SALON = 'SALON';
    case TERRAZA = 'TERRAZA';
    case BAR = 'BAR';

    public function label(): string
    {
        return match ($this) {
            self::SALON => 'Salón',
            self::TERRAZA => 'Terraza',
            self::BAR => 'Bar',
        };
    }
}
