<?php

namespace App\Services\Gaveta;

use App\Contracts\GavetaDriverContract;
use App\Models\Impresora;
use App\Services\Printing\PrinterConnectorFactory;
use Mike42\Escpos\Printer;

class PulseGavetaDriver implements GavetaDriverContract
{
    public function __construct(
        private readonly PrinterConnectorFactory $connectorFactory,
    ) {}

    public function esAutomatico(): bool
    {
        return true;
    }

    public function abrir(Impresora $impresora): void
    {
        $printer = new Printer($this->connectorFactory->create($impresora));

        try {
            $printer->pulse();
        } finally {
            try {
                $printer->close();
            } catch (\Throwable) {
                // Apertura no bloqueante: el cierre del conector no debe
                // ocultar un error previo ni escalar el fallo.
            }
        }
    }
}
