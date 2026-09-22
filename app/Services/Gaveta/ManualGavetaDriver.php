<?php

namespace App\Services\Gaveta;

use App\Contracts\GavetaDriverContract;
use App\Models\Impresora;

class ManualGavetaDriver implements GavetaDriverContract
{
    public function esAutomatico(): bool
    {
        return false;
    }

    public function abrir(Impresora $impresora): void
    {
        // Sin hardware acoplado: la gaveta se abre físicamente con la llave y
        // la confirmación se registra en la interfaz del turno.
    }
}
