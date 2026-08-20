<?php

namespace App\Http\Controllers\Printing;

use App\Http\Controllers\Controller;
use App\Models\Impresora;
use App\Models\TrabajoImpresion;
use App\Services\Printing\PdfTicketService;
use Illuminate\Http\Response;

class TicketPdfController extends Controller
{
    public function __construct(
        private readonly PdfTicketService $pdfTicketService,
    ) {}

    public function verTrabajoPdf(TrabajoImpresion|int|string $trabajo): Response
    {
        $job = $trabajo instanceof TrabajoImpresion
            ? $trabajo
            : TrabajoImpresion::query()->findOrFail($trabajo);

        return $this->pdfTicketService->streamJobPdf($job);
    }

    public function verPruebaPdf(Impresora|int|string $impresora): Response
    {
        $printer = $impresora instanceof Impresora
            ? $impresora
            : Impresora::query()->findOrFail($impresora);

        $pdf = $this->pdfTicketService->generateTestPdf($printer->nombre, $printer->tipo->label());

        return $pdf->stream("Prueba-{$printer->nombre}.pdf");
    }
}
