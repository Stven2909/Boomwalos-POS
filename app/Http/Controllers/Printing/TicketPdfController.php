<?php

namespace App\Http\Controllers\Printing;

use App\Http\Controllers\Controller;
use App\Models\Impresora;
use App\Models\TrabajoImpresion;
use App\Services\Printing\PdfTicketService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class TicketPdfController extends Controller
{
    public function __construct(
        private readonly PdfTicketService $pdfTicketService,
    ) {}

    public function verTrabajoPdf(TrabajoImpresion|int|string $trabajo): Response
    {
        try {
            $id = $trabajo instanceof TrabajoImpresion ? $trabajo->getKey() : $trabajo;
            $job = $trabajo instanceof TrabajoImpresion
                ? $trabajo
                : TrabajoImpresion::query()->find($id);

            if (! $job) {
                return response(
                    view('printing.ticket-fallback', [
                        'titulo' => "Trabajo #{$id}",
                        'contenido' => "TRABAJO DE IMPRESIÓN #{$id}\n--------------------------------\nNo se encontró el registro en la base de datos.",
                        'error' => "El trabajo #{$id} no existe.",
                    ]),
                    404,
                    ['Content-Type' => 'text/html; charset=UTF-8']
                );
            }

            return $this->pdfTicketService->streamJobPdf($job);
        } catch (Throwable $e) {
            Log::error("Error al generar PDF de trabajo: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
            ]);

            $contenido = isset($job) && $job ? ($job->contenido ?? '') : "Error al procesar trabajo: {$e->getMessage()}";
            $titulo = isset($job) && $job ? (($job->isTicket() ? 'Ticket #' : 'Comanda #') . $job->getKey()) : 'Ticket';

            return response(
                view('printing.ticket-fallback', [
                    'titulo' => $titulo,
                    'contenido' => $contenido,
                    'error' => $e->getMessage(),
                ]),
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            );
        }
    }

    public function verPruebaPdf(Impresora|int|string $impresora): Response
    {
        try {
            $id = $impresora instanceof Impresora ? $impresora->getKey() : $impresora;
            $printer = $impresora instanceof Impresora
                ? $impresora
                : Impresora::query()->find($id);

            if (! $printer) {
                return response(
                    view('printing.ticket-fallback', [
                        'titulo' => "Impresora #{$id}",
                        'contenido' => "IMPRESORA #{$id}\n--------------------------------\nNo se encontró la impresora en la base de datos.",
                    ]),
                    404,
                    ['Content-Type' => 'text/html; charset=UTF-8']
                );
            }

            $pdf = $this->pdfTicketService->generateTestPdf($printer->nombre, $printer->tipo->label());

            return $pdf->stream("Prueba-{$printer->nombre}.pdf");
        } catch (Throwable $e) {
            Log::error("Error al generar PDF de prueba: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
            ]);

            return response(
                view('printing.ticket-fallback', [
                    'titulo' => 'Prueba de Impresión',
                    'contenido' => "PRUEBA DE IMPRESORA\n--------------------------------\nImpresora: " . ($printer->nombre ?? 'Virtual') . "\nFecha: " . now()->format('d/m/Y H:i') . "\n--------------------------------\nESTADO: CONEXIÓN VIRTUAL OK",
                    'error' => $e->getMessage(),
                ]),
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            );
        }
    }
}
