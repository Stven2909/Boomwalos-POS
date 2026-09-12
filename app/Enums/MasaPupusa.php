<?php

namespace App\Enums;

enum MasaPupusa: string
{
    case MAIZ = 'MAIZ';
    case ARROZ = 'ARROZ';

    public function label(): string
    {
        return match ($this) {
            self::MAIZ => 'Maíz',
            self::ARROZ => 'Arroz',
        };
    }
}