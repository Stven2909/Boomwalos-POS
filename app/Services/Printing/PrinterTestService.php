<?php

namespace App\Services\Printing;

use App\Enums\TipoConexionImpresora;
use App\Enums\TipoSonidoImpresora;
use App\Models\Impresora;
use Illuminate\Support\Facades\Storage;
use Mike42\Escpos\Printer;

class PrinterTestService
{
    public function __construct(
        private readonly PrinterConnectorFactory $connectorFactory,
        private readonly PdfTicketService $pdfTicketService,
        private readonly PrinterBeeper $beeper,
    ) {}

    public function probar(Impresora $impresora): ?string
    {
        if ($impresora->conexion === TipoConexionImpresora::PDF) {
            $pdf = $this->pdfTicketService->generateTestPdf($impresora->nombre, $impresora->tipo->label());
            $filePath = "impresiones/prueba-{$impresora->getKey()}.pdf";
            Storage::disk('public')->put($filePath, $pdf->output());

            $impresora->update(['ultima_conexion_exitosa_at' => now()]);

            return route('impresion.prueba.pdf', ['impresora' => $impresora->getKey()]);
        }

        $printer = new Printer($this->connectorFactory->create($impresora));

        $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_HEIGHT);
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("PRUEBA DE IMPRESION\n");

        $printer->selectPrintMode(Printer::MODE_FONT_A);
        $printer->text("{$impresora->nombre}\n");
        $printer->text($impresora->direccionConexion() . "\n");
        $printer->text(now()->format('d/m/Y H:i:s') . "\n");

        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->feed();
        $printer->text("Conexion OK\n");
        $printer->feed();
        $this->beeper->sonar($printer, $impresora);
        $printer->cut();
        $printer->close();

        $impresora->update(['ultima_conexion_exitosa_at' => now()]);

        return null;
    }

    /**
     * Envía únicamente el pitido, sin imprimir papel. Sirve para identificar
     * qué comando responde cada marca de impresora.
     */
    public function probarSonido(Impresora $impresora): void
    {
        if ($impresora->conexion === TipoConexionImpresora::PDF) {
            throw new \RuntimeException('La impresora virtual no emite sonido.');
        }

        if ($impresora->sonidoEfectivo() === TipoSonidoImpresora::NINGUNO) {
            throw new \RuntimeException('Esta impresora está configurada en "Sin sonido".');
        }

        $printer = new Printer($this->connectorFactory->create($impresora));
        $this->beeper->sonar($printer, $impresora);
        $printer->close();

        $impresora->update(['ultima_conexion_exitosa_at' => now()]);
    }
}
