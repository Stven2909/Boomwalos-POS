<?php

namespace App\Services\Printing;

use App\Enums\TipoConexionImpresora;
use App\Models\Impresora;
use App\Rules\WindowsPrinterPath;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\MemoryPrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\PrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use RuntimeException;

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
            TipoConexionImpresora::PDF => new MemoryPrintConnector(),
            TipoConexionImpresora::WINDOWS => $this->createWindows($impresora),
        };
    }

    public function createMemory(): PrintConnector
    {
        return new MemoryPrintConnector();
    }

    private function createWindows(Impresora $impresora): PrintConnector
    {
        $destination = WindowsPrinterPath::toSmb($impresora->ruta_windows ?? '');

        if (! $this->isWindows()) {
            throw new RuntimeException('La conexión Windows compartida requiere ejecutar PHP en Windows con acceso a la impresora.');
        }

        return new WindowsPrintConnector($destination);
    }

    protected function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }
}
