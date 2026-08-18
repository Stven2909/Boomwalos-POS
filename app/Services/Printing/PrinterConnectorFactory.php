<?php

namespace App\Services\Printing;

use App\Enums\TipoConexionImpresora;
use App\Models\Impresora;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\MemoryPrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\PrintConnector;

class PrinterConnectorFactory
{
    public function create(Impresora $impresora): PrintConnector
    {
        return match ($impresora->conexion) {
            TipoConexionImpresora::RED => new NetworkPrintConnector(
                $impresora->ip,
                $impresora->puerto ?? 9100,
            ),
            TipoConexionImpresora::USB => new FilePrintConnector(
                $impresora->dispositivo_usb,
            ),
        };
    }

    public function createMemory(): PrintConnector
    {
        return new MemoryPrintConnector();
    }
}
