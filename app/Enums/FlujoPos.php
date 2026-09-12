<?php

namespace App\Enums;

enum FlujoPos: string
{
    case MOSTRADOR_PREPAGO = 'MOSTRADOR_PREPAGO';
    case MESA_POSTPAGO = 'MESA_POSTPAGO';

    public function label(): string
    {
        return match ($this) {
            self::MOSTRADOR_PREPAGO => 'Mostrador — cobrar antes',
            self::MESA_POSTPAGO => 'Mesa — cobrar después',
        };
    }

    public function tipoPedido(): TipoPedido
    {
        return match ($this) {
            self::MOSTRADOR_PREPAGO => TipoPedido::PARA_LLEVAR,
            self::MESA_POSTPAGO => TipoPedido::MESA,
        };
    }

    public static function fromTipoPedido(TipoPedido $tipoPedido): self
    {
        return match ($tipoPedido) {
            TipoPedido::PARA_LLEVAR => self::MOSTRADOR_PREPAGO,
            TipoPedido::MESA => self::MESA_POSTPAGO,
        };
    }
}
