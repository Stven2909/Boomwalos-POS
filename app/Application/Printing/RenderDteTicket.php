<?php

namespace App\Application\Printing;

use App\Models\VentaFiscalPos;
use Illuminate\Support\Carbon;

/**
 * Representación impresa resumida del DTE final (ya sellado por Hacienda),
 * para reimprimir en el kiosko cuando la venta diferida por contingencia se
 * sincroniza y el comprobante transitorio deja de estar vigente.
 */
class RenderDteTicket
{
    public function render(VentaFiscalPos $venta): string
    {
        $lineas = [
            'DOCUMENTO TRIBUTARIO ELECTRÓNICO',
            'EMISIÓN DIFERIDA (CONTINGENCIA)',
            'Referencia: ' . ($venta->referencia ?? '—'),
            str_repeat('-', 32),
            'Código de generación:',
            (string) ($venta->codigo_generacion ?? '—'),
            'Número de control:',
            (string) ($venta->numero_control ?? '—'),
            'Sello de recepción:',
            (string) ($venta->sello_recepcion ?? '—'),
            'Fecha y hora de emisión: ' . $this->fechaEmision($venta),
            'Total: $' . number_format((float) $venta->monto_total, 2),
            str_repeat('-', 32),
            'Documento aceptado por el Ministerio de Hacienda.',
            'Conserve este comprobante.',
            str_repeat('-', 32),
        ];

        return implode("\n", $lineas) . "\n";
    }

    private function fechaEmision(VentaFiscalPos $venta): string
    {
        $fecha = $venta->fecha_emision;

        if ($fecha instanceof \DateTimeInterface) {
            return Carbon::instance($fecha)->setTimezone('America/El_Salvador')->format('d/m/Y H:i');
        }

        return now()->setTimezone('America/El_Salvador')->format('d/m/Y H:i');
    }
}
