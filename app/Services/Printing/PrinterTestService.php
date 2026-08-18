<?php

namespace App\Services\Printing;

use App\Models\Impresora;
use Mike42\Escpos\Printer;

class PrinterTestService
{
    public function __construct(
        private readonly PrinterConnectorFactory $connectorFactory,
    ) {}

    public function probar(Impresora $impresora): void
    {
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
        $printer->cut();
        $printer->close();

        $impresora->update(['ultima_conexion_exitosa_at' => now()]);
    }
}
