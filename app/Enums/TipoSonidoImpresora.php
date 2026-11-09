<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TipoSonidoImpresora: string implements HasLabel
{
    case BEL = 'bel';
    case ESC_B = 'esc_b';
    case NINGUNO = 'ninguno';

    public function label(): string
    {
        return match ($this) {
            self::BEL => 'Pitido estándar (BEL)',
            self::ESC_B => 'Pitido ESC B (Epson / SEWOO)',
            self::NINGUNO => 'Sin sonido',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    /**
     * Bytes ESC/POS que se envían a la impresora al terminar el trabajo.
     */
    public function comando(): string
    {
        return match ($this) {
            self::BEL => "\x07",
            self::ESC_B => "\x1B\x42",
            self::NINGUNO => '',
        };
    }
}
