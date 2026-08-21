<?php

namespace App\Services\Printing;

use App\Models\TrabajoImpresion;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class PdfDocument
{
    public function __construct(private readonly string $content) {}

    public function output(): string
    {
        return $this->content;
    }

    public function stream(string $filename = 'document.pdf'): Response
    {
        return response($this->content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
}

class PdfTicketService
{
    public function renderToPdf(string $contenido, string $titulo = 'Ticket'): PdfDocument
    {
        $fontDir = storage_path('fonts');
        if (! is_dir($fontDir)) {
            @mkdir($fontDir, 0777, true);
        }
        if (! is_writable($fontDir)) {
            $fontDir = sys_get_temp_dir();
        }

        $lineas = explode("\n", $contenido);
        $totalLineas = max(count($lineas), 15);
        $altoMm = max(120, (int) ($totalLineas * 5.5) + 30);
        $anchoPt = 226.77; // 80mm en puntos tipográficos (72 pt / pulgada)
        $altoPt = ($altoMm / 25.4) * 72;

        $html = view('printing.ticket-pdf', [
            'lineas' => $lineas,
            'titulo' => $titulo,
            'altoMm' => $altoMm,
        ])->render();

        $pdfBinary = '';

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            try {
                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)
                    ->setPaper([0, 0, $anchoPt, $altoPt], 'portrait')
                    ->setOptions([
                        'font_dir' => $fontDir,
                        'font_cache' => $fontDir,
                        'temp_dir' => sys_get_temp_dir(),
                        'chroot' => base_path(),
                        'enable_remote' => true,
                        'default_font' => 'Courier',
                        'isHtml5ParserEnabled' => true,
                    ]);

                $pdfBinary = (string) $pdf->output();
            } catch (\Throwable) {
                $pdfBinary = '';
            }
        }

        if ($pdfBinary === '') {
            $options = new Options();
            $options->set('fontDir', $fontDir);
            $options->set('fontCache', $fontDir);
            $options->set('tempDir', sys_get_temp_dir());
            $options->set('chroot', base_path());
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'Courier');
            $options->set('isHtml5ParserEnabled', true);

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper([0, 0, $anchoPt, $altoPt], 'portrait');
            $dompdf->render();

            $pdfBinary = (string) $dompdf->output();
        }

        return new PdfDocument($pdfBinary);
    }

    public function saveForJob(TrabajoImpresion $job): string
    {
        $titulo = ($job->isTicket() ? 'Ticket #' : 'Comanda #') . $job->getKey();
        $doc = $this->renderToPdf($job->contenido ?? '', $titulo);
        $fileName = "impresiones/trabajo-{$job->getKey()}.pdf";

        Storage::disk('public')->put($fileName, $doc->output());

        return $fileName;
    }

    public function streamJobPdf(TrabajoImpresion $job): Response
    {
        $titulo = ($job->isTicket() ? 'Ticket #' : 'Comanda #') . $job->getKey();
        $doc = $this->renderToPdf($job->contenido ?? '', $titulo);

        return $doc->stream("{$titulo}.pdf");
    }

    public function generateTestPdf(string $nombreImpresora, string $tipo): PdfDocument
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
