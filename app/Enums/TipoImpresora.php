<?php

namespace App\Enums;

enum TipoImpresora: string
{
    case TICKET = 'TICKET';
    case COMANDA = 'COMANDA';

    public function label(): string
    {
        return match ($this) {
            self::TICKET => 'Ticket',
            self::COMANDA => 'Comanda',
        };
    }
}
