<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TipoSonidoImpresora: string implements HasLabel
{
    case BEL = 'bel';
    case ESC_B = 'esc_b';
    case RJ11_PIN2 = 'rj11_pin2';
    case RJ11_PIN5 = 'rj11_pin5';
    case EPSON_MELODY = 'epson_melody';
    case NINGUNO = 'ninguno';

    public function label(): string
    {
        return match ($this) {
            self::BEL => 'Pitido estándar (BEL - 0x07)',
            self::ESC_B => 'Buzzer interno ESC B (3 pitidos)',
            self::RJ11_PIN2 => 'Buzzer externo / Puerto RJ11 (Pin 2 - ESC p 0)',
            self::RJ11_PIN5 => 'Buzzer externo / Puerto RJ11 (Pin 5 - ESC p 1)',
            self::EPSON_MELODY => 'Melodía Epson oficial (GS ( A)',
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
            // ESC B n t (n=3 pitidos, t=5x100ms - estándar POS-80 / J-Speed / Sewoo)
            self::ESC_B => "\x1B\x42\x03\x05",
            // ESC p m t1 t2 (m=0 Pin 2, t1=50ms on, t2=50ms off)
            self::RJ11_PIN2 => "\x1B\x70\x00\x19\x19",
            // ESC p m t1 t2 (m=1 Pin 5, t1=50ms on, t2=50ms off)
            self::RJ11_PIN5 => "\x1B\x70\x01\x19\x19",
            // ESC ( A pL pH fn n c (Buzzer integrado Epson nativo)
            self::EPSON_MELODY => "\x1B\x28\x41\x03\x00\x61\x01\x01",
            self::NINGUNO => '',
        };
    }
}
