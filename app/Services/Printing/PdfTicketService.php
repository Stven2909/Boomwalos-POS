<?php

namespace App\Services\Printing;

use App\Models\TrabajoImpresion;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfWrapper;
use Illuminate\Support\Facades\Storage;

class PdfTicketService
{
    public function renderToPdf(string $contenido, string $titulo = 'Ticket'): DomPdfWrapper
    {
        $lineas = explode("\n", $contenido);
        $totalLineas = max(count($lineas), 15);
        $altoMm = max(120, (int) ($totalLineas * 5.5) + 30);
        $anchoPt = 226.77; // 80mm en puntos tipográficos (72 pt / pulgada)
        $altoPt = ($altoMm / 25.4) * 72;

        return Pdf::loadView('printing.ticket-pdf', [
            'lineas' => $lineas,
            'titulo' => $titulo,
            'altoMm' => $altoMm,
        ])
            ->setPaper([0, 0, $anchoPt, $altoPt], 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);
    }

    public function saveForJob(TrabajoImpresion $job): string
    {
        $titulo = ($job->isTicket() ? 'Ticket #' : 'Comanda #') . $job->getKey();
        $pdf = $this->renderToPdf($job->contenido ?? '', $titulo);
        $fileName = "impresiones/trabajo-{$job->getKey()}.pdf";

        Storage::disk('public')->put($fileName, $pdf->output());

        return $fileName;
    }

    public function streamJobPdf(TrabajoImpresion $job): \Illuminate\Http\Response
    {
        $titulo = ($job->isTicket() ? 'Ticket #' : 'Comanda #') . $job->getKey();
        $pdf = $this->renderToPdf($job->contenido ?? '', $titulo);

        return $pdf->stream("{$titulo}.pdf");
    }

    public function generateTestPdf(string $nombreImpresora, string $tipo): \Barryvdh\DomPDF\PDF
    {
        $fecha = now()->setTimezone('America/El_Salvador')->format('d/m/Y H:i:s');
        $contenido = implode("\n", [
            'BOOMWALOS POS',
            'PRUEBA DE IMPRESORA VIRTUAL',
            '--------------------------------',
            "IMPRESORA: {$nombreImpresora}",
            "TIPO: {$tipo}",
            "CONEXION: SIMULADOR PDF",
            "FECHA: {$fecha}",
            '--------------------------------',
            'ESTADO: CONEXION VIRTUAL OK',
            'FORMATO: TERMICO 80MM',
            '--------------------------------',
            'Este documento confirma que el',
            'sistema puede generar comandas',
            'y tickets en formato PDF sin',
            'requerir hardware fisico.',
            '--------------------------------',
            'GRACIAS POR SU PREFERENCIA',
        ]);

        return $this->renderToPdf($contenido, "Prueba {$nombreImpresora}");
    }
}
