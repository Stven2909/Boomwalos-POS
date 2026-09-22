<?php

namespace App\Contracts;

use App\Models\Impresora;

interface GavetaDriverContract
{
    public function esAutomatico(): bool;

    public function abrir(Impresora $impresora): void;
}
