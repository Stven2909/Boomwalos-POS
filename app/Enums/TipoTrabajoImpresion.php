<?php

namespace App\Enums;

enum TipoTrabajoImpresion: string
{
    case TICKET = 'TICKET';
    case COMANDA = 'COMANDA';
    case CORTE_CAJA = 'CORTE_CAJA';

    public function label(): string
    {
        return match ($this) {
            self::TICKET => 'Ticket',
            self::COMANDA => 'Comanda',
            self::CORTE_CAJA => 'Corte de caja',
        };
    }
}
