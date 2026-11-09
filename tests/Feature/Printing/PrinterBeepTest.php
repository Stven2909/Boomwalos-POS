<?php

namespace Tests\Feature\Printing;

use App\Enums\EstadoImpresion;
use App\Enums\TipoConexionImpresora;
use App\Enums\TipoImpresora;
use App\Enums\TipoTrabajoImpresion;
use App\Models\Impresora;
use App\Models\TrabajoImpresion;
use App\Services\Printing\EscPosPrintService;
use App\Services\Printing\PrinterConnectorFactory;
use App\Services\Printing\PrinterTestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mike42\Escpos\PrintConnectors\MemoryPrintConnector;
use Mike42\Escpos\PrintConnectors\PrintConnector;
use Tests\TestCase;

class PrinterBeepTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_impresoras_sin_configuracion_piten_por_defecto(): void
    {
        $impresora = $this->impresora();

        $this->assertSame('bel', $impresora->sonido);
        $this->assertSame("\x07", $impresora->comandoSonido());
    }

    public function test_el_ajuste_de_sonido_se_guarda_en_la_configuracion(): void
    {
        $impresora = $this->impresora();

        $impresora->sonido = 'esc_b';

        $this->assertSame('esc_b', data_get($impresora->configuracion, 'sonido'));
        $this->assertSame("\x1B\x42\x03\x02", $impresora->comandoSonido());
    }

    public function test_el_trabajo_impreso_envia_un_pitido_bel_por_defecto(): void
    {
        $factory = $this->fakeFactory();
        $job = $this->trabajo($this->impresora());

        app(EscPosPrintService::class)->print($job->getKey());

        $this->assertStringContainsString("\x07", $factory->connector->getData());
    }

    public function test_el_trabajo_impreso_envia_el_pitido_esc_b_configurado(): void
    {
        $factory = $this->fakeFactory();
        $job = $this->trabajo($this->impresora('esc_b'));

        app(EscPosPrintService::class)->print($job->getKey());

        $this->assertStringContainsString("\x1B\x42", $factory->connector->getData());
    }

    public function test_no_se_envia_pitido_cuando_la_impresora_esta_en_sin_sonido(): void
    {
        $factory = $this->fakeFactory();
        $job = $this->trabajo($this->impresora('ninguno'));

        app(EscPosPrintService::class)->print($job->getKey());

        $datos = $factory->connector->getData();

        $this->assertStringNotContainsString("\x07", $datos);
        $this->assertStringNotContainsString("\x1B\x42", $datos);
    }

    public function test_probar_sonido_envia_solo_el_pitido_sin_imprimir(): void
    {
        $factory = $this->fakeFactory();
        $impresora = $this->impresora();

        app(PrinterTestService::class)->probarSonido($impresora);

        $this->assertSame("\x07", $factory->connector->getData());
    }

    public function test_probar_sonido_avisa_cuando_la_impresora_esta_en_sin_sonido(): void
    {
        $this->fakeFactory();

        $this->expectException(\RuntimeException::class);

        app(PrinterTestService::class)->probarSonido($this->impresora('ninguno'));
    }

    private function impresora(string $sonido = 'bel'): Impresora
    {
        return Impresora::create([
            'nombre' => 'Caja '.$sonido,
            'tipo' => TipoImpresora::TICKET,
            'conexion' => TipoConexionImpresora::RED,
            'ip' => '127.0.0.1',
            'puerto' => 9100,
            'activa' => true,
            'configuracion' => ['sonido' => $sonido],
        ]);
    }

    private function trabajo(Impresora $impresora): TrabajoImpresion
    {
        return TrabajoImpresion::create([
            'impresora_id' => $impresora->getKey(),
            'tipo_trabajo' => TipoTrabajoImpresion::TICKET,
            'estado' => EstadoImpresion::PENDIENTE,
            'contenido' => "PUPUSERIA BOOMWALOS\nTICKET #100\nTOTAL: $5.00\n",
            'intentos' => 0,
        ]);
    }

    private function fakeFactory(): PrinterConnectorFactory
    {
        $factory = new class extends PrinterConnectorFactory
        {
            public ?CapturingPrintConnector $connector = null;

            public function create(Impresora $impresora): PrintConnector
            {
                return $this->connector ??= new CapturingPrintConnector;
            }
        };

        $this->app->instance(PrinterConnectorFactory::class, $factory);

        return $factory;
    }
}

/**
 * Captura todos los bytes enviados al conector interceptando write(),
 * porque MemoryPrintConnector vacía su buffer en finalize() antes de
 * devolverlo y luego lo deja en null, lo que rompe getData() tras close().
 */
class CapturingPrintConnector extends MemoryPrintConnector
{
    private string $captured = '';

    public function write(string $data): void
    {
        $this->captured .= $data;
        parent::write($data);
    }

    public function getData(): string
    {
        return $this->captured;
    }
}
