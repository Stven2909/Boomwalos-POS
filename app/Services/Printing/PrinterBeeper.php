<?php

namespace App\Services\Printing;

use App\Enums\TipoConexionImpresora;
use App\Models\Impresora;
use Illuminate\Support\Facades\Log;
use Mike42\Escpos\Printer;

class PrinterBeeper
{
    /**
     * Envía el pitido configurado por impresora. Un fallo de sonido nunca
     * invalida el trabajo de impresión: solo se registra la advertencia.
     */
    public function sonar(Printer $printer, ?Impresora $impresora): void
    {
        if (! $impresora || $impresora->conexion === TipoConexionImpresora::PDF) {
            return;
        }

        $comando = $impresora->comandoSonido();

        if ($comando === '') {
            return;
        }

        try {
            $printer->textRaw($comando);
        } catch (\Throwable $e) {
            Log::warning("No se pudo enviar el sonido a la impresora #{$impresora->getKey()}: {$e->getMessage()}");
        }
    }
}
