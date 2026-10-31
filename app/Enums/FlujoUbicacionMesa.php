<?php

namespace App\Enums;

/**
 * Determina qué se muestra al abrir una ubicación en el POS.
 *
 * MESAS  → plano de mesas (flujo comer aquí / cobrar después).
 * LLEVAR → tablero de pedidos para llevar (flujo mostrador / cobrar antes).
 */
enum FlujoUbicacionMesa: string
{
    case MESAS = 'MESAS';
    case LLEVAR = 'LLEVAR';

    public function label(): string
    {
        return match ($this) {
            self::MESAS => 'Mesas',
            self::LLEVAR => 'Para llevar',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::MESAS => 'Muestra el plano de mesas de la ubicación.',
            self::LLEVAR => 'Muestra el tablero de pedidos para llevar, sin mesas.',
        };
    }

    public function esParaLlevar(): bool
    {
        return $this === self::LLEVAR;
    }
}
