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

    public function verTrabajoPdf(TrabajoImpresion $trabajo): Response
    {
        return $this->pdfTicketService->streamJobPdf($trabajo);
    }

    public function verPruebaPdf(Impresora $impresora): Response
    {
        $pdf = $this->pdfTicketService->generateTestPdf($impresora->nombre, $impresora->tipo->label());

        return $pdf->stream("Prueba-{$impresora->nombre}.pdf");
    }
}
