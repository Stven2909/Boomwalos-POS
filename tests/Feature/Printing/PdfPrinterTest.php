<?php

namespace Tests\Feature\Printing;

use App\Enums\EstadoImpresion;
use App\Enums\TipoConexionImpresora;
use App\Enums\TipoImpresora;
use App\Enums\TipoTrabajoImpresion;
use App\Models\Establecimiento;
use App\Models\Impresora;
use App\Models\TrabajoImpresion;
use App\Models\User;
use App\Services\Printing\EscPosPrintService;
use App\Services\Printing\PdfTicketService;
use App\Services\Printing\PrinterTestService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdfPrinterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Establecimiento $establecimiento;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermissionsSeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'admin@test.com',
            'usuario' => 'admin_test',
        ]);
        $this->admin->assignRole('administrador');

        $this->establecimiento = Establecimiento::create([
            'nombre' => 'Sucursal Demo',
            'direccion' => 'Calle Principal #123',
        ]);
    }

    public function test_pdf_ticket_service_generates_valid_pdf(): void
    {
        $service = app(PdfTicketService::class);
        $pdf = $service->renderToPdf("LINEA 1\nLINEA 2\nTOTAL: $10.00", 'Test');

        $output = $pdf->output();

        $this->assertNotEmpty($output);
        $this->assertStringStartsWith('%PDF-', $output);
    }

    public function test_esc_pos_service_prints_pdf_printer_without_error(): void
    {
        $impresora = Impresora::create([
            'nombre' => 'Caja Virtual',
            'tipo' => TipoImpresora::TICKET,
            'conexion' => TipoConexionImpresora::PDF,
            'activa' => true,
            'establecimiento_id' => $this->establecimiento->getKey(),
        ]);

        $job = TrabajoImpresion::create([
            'impresora_id' => $impresora->getKey(),
            'tipo_trabajo' => TipoTrabajoImpresion::TICKET,
            'estado' => EstadoImpresion::PENDIENTE,
            'contenido' => "PUPUSERIA BOOMWALOS\nTICKET #100\nTOTAL: $5.00\n",
            'intentos' => 0,
        ]);

        app(EscPosPrintService::class)->print($job->getKey());

        $job->refresh();
        $this->assertSame(EstadoImpresion::IMPRESO, $job->estado);
        $this->assertNotNull($job->impreso_at);
        $this->assertNull($job->ultimo_error);
    }

    public function test_printer_test_service_generates_pdf_for_pdf_printer(): void
    {
        $impresora = Impresora::create([
            'nombre' => 'Cocina Virtual',
            'tipo' => TipoImpresora::COMANDA,
            'conexion' => TipoConexionImpresora::PDF,
            'activa' => true,
            'establecimiento_id' => $this->establecimiento->getKey(),
        ]);

        $url = app(PrinterTestService::class)->probar($impresora);

        $this->assertNotNull($url);
        $this->assertStringContainsString("impresion/prueba/{$impresora->getKey()}/pdf", $url);
        $this->assertNotNull($impresora->fresh()->ultima_conexion_exitosa_at);
    }

    public function test_web_route_serves_job_pdf_inline(): void
    {
        $impresora = Impresora::create([
            'nombre' => 'Caja Virtual',
            'tipo' => TipoImpresora::TICKET,
            'conexion' => TipoConexionImpresora::PDF,
            'activa' => true,
        ]);

        $job = TrabajoImpresion::create([
            'impresora_id' => $impresora->getKey(),
            'tipo_trabajo' => TipoTrabajoImpresion::TICKET,
            'estado' => EstadoImpresion::IMPRESO,
            'contenido' => "TICKET #1\nPUPUSAS DE QUESO\nTOTAL: $4.00",
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('impresion.trabajo.pdf', ['trabajo' => $job->getKey()]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_web_route_serves_printer_test_pdf(): void
    {
        $impresora = Impresora::create([
            'nombre' => 'Cocina PDF',
            'tipo' => TipoImpresora::COMANDA,
            'conexion' => TipoConexionImpresora::PDF,
            'activa' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('impresion.prueba.pdf', ['impresora' => $impresora->getKey()]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
