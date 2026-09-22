<?php

namespace Tests\Unit;

use App\Enums\TipoConexionImpresora;
use App\Enums\TipoImpresora;
use App\Models\Impresora;
use App\Services\Gaveta\ManualGavetaDriver;
use App\Services\Gaveta\PulseGavetaDriver;
use App\Services\Printing\PrinterConnectorFactory;
use Mike42\Escpos\PrintConnectors\MemoryPrintConnector;
use Mike42\Escpos\Printer;
use Tests\TestCase;

class GavetaDriverTest extends TestCase
{
    public function test_manual_driver_no_es_automatico_y_abrir_no_despacha_hardware(): void
    {
        $driver = new ManualGavetaDriver;
        $impresora = new Impresora([
            'tipo' => TipoImpresora::TICKET,
            'conexion' => TipoConexionImpresora::PDF,
        ]);

        $this->assertFalse($driver->esAutomatico());
        $driver->abrir($impresora);
        $this->assertTrue(true);
    }

    public function test_printer_pulse_envia_comando_esc_p(): void
    {
        $connector = new MemoryPrintConnector;
        $printer = new Printer($connector);

        $printer->pulse();
        $bytes = $connector->getData();
        $printer->close();

        $this->assertStringContainsString("\x1bp", $bytes);
    }

    public function test_pulse_driver_abre_la_registradora_sin_lanzar_con_conector_memoria(): void
    {
        $impresora = new Impresora([
            'tipo' => TipoImpresora::TICKET,
            'conexion' => TipoConexionImpresora::PDF,
        ]);

        $driver = new PulseGavetaDriver(new PrinterConnectorFactory);

        $this->assertTrue($driver->esAutomatico());
        $driver->abrir($impresora);
        $this->assertTrue(true);
    }
}
