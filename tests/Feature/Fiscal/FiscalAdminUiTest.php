<?php

namespace Tests\Feature\Fiscal;

use App\Application\Fiscal\FiscalOutboxService;
use App\Enums\EstadoColaVentaFiscal;
use App\Enums\EstadoDocumentoFiscal;
use App\Enums\EstadoMesa;
use App\Enums\EstadoVentaFiscal;
use App\Enums\MetodoPago;
use App\Enums\TipoDocumento;
use App\Enums\TipoPedido;
use App\Enums\ZonaMesa;
use App\Filament\Resources\ConfiguracionFiscal\ConfiguracionFiscalResource;
use App\Filament\Resources\ConfiguracionFiscal\Pages\ManageConfiguracionFiscal;
use App\Filament\Resources\DocumentoFiscal\DocumentoFiscalResource;
use App\Filament\Resources\DocumentoFiscal\Pages\ManageDocumentosFiscales;
use App\Filament\Resources\VentaFiscalPos\Pages\ManageVentasFiscales;
use App\Filament\Resources\VentaFiscalPos\VentaFiscalPosResource;
use App\Jobs\EnviarVentasFiscalesJob;
use App\Models\Categoria;
use App\Models\ColaVentaFiscal;
use App\Models\ConfiguracionFiscal;
use App\Models\DocumentoFiscal;
use App\Models\Establecimiento;
use App\Models\Impresora;
use App\Models\Mesa;
use App\Models\Producto;
use App\Models\SesionCaja;
use App\Models\User;
use App\Models\VentaFiscalPos;
use App\Services\CobroService;
use App\Services\PedidoService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class FiscalAdminUiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

    private Establecimiento $establishment;

    private Mesa $table;

    private Producto $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesPermissionsSeeder::class);

        $this->admin = User::factory()->create([
            'usuario' => '1',
            'password' => '1234',
        ]);
        $this->admin->assignRole('administrador');

        $this->cashier = User::factory()->create([
            'usuario' => '21',
            'password' => '1234',
        ]);
        $this->cashier->assignRole('cajero');

        $this->establishment = Establecimiento::create([
            'nombre' => 'Pupusería Demo',
            'direccion' => 'Dirección de prueba',
        ]);

        $this->table = Mesa::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'numero' => '8',
            'zona' => ZonaMesa::SALON,
            'estado' => EstadoMesa::LIBRE,
        ]);

        $category = Categoria::create(['nombre' => 'Bebidas Frías']);
        $this->product = Producto::create([
            'categoria_id' => $category->getKey(),
            'nombre' => 'Limonada fresca',
            'precio' => 4,
            'disponibilidad' => 'DISPONIBLE',
        ]);

        SesionCaja::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'usuario_apertura_id' => $this->cashier->getKey(),
            'monto_inicial' => 0,
            'fecha_apertura' => now(),
        ]);

        Impresora::create([
            'nombre' => 'Cocina',
            'tipo' => 'COMANDA',
            'configuracion' => ['driver' => 'queue'],
        ]);

        ConfiguracionFiscal::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'fiscal_habilitada' => true,
            'cliente_key' => 'est-test',
            'cliente_secret' => (string) config('fiscal.mock.secret'),
            'intentos_maximos' => 3,
        ]);
    }

    private function syncVenta(): VentaFiscalPos
    {
        Queue::fake();
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::MESA, $this->cashier, $this->table->getKey());
        $service->addProduct($pedido, $this->product, $this->cashier);
        $service->sendPendingBatch($pedido, $this->cashier);

        $pago = app(CobroService::class)->charge(
            $pedido,
            MetodoPago::EFECTIVO,
            '10.00',
            $this->cashier,
        );

        $venta = VentaFiscalPos::where('pago_id', $pago->getKey())->firstOrFail();

        Http::fake([
            '*' => Http::response([
                'fiscal_sale_id' => 'MOCK-UI-202',
                'estado' => 'RECIBIDA',
                'qr_url' => null,
            ], 202),
        ]);

        (new EnviarVentasFiscalesJob($venta->getKey()))->handle(app(FiscalOutboxService::class));

        return $venta->fresh();
    }

    public function test_configuracion_admin_accede_a_la_pagina(): void
    {
        $this->actingAs($this->admin)
            ->get(ManageConfiguracionFiscal::getUrl())
            ->assertOk();
    }

    public function test_configuracion_cajero_no_accede(): void
    {
        $this->actingAs($this->cashier)
            ->get(ManageConfiguracionFiscal::getUrl())
            ->assertForbidden();
    }

    public function test_configuracion_creacion_guardael_secret_cifrado(): void
    {
        $this->actingAs($this->admin);

        $otro = Establecimiento::create([
            'nombre' => 'Pupusería Demo Dos',
            'direccion' => 'Segunda dirección',
        ]);

        Livewire::test(ManageConfiguracionFiscal::class)
            ->callAction('create', data: [
                'establecimiento_id' => $otro->getKey(),
                'fiscal_habilitada' => true,
                'cliente_key' => 'est-ui',
                'cliente_secret' => 'super-secreto',
                'intentos_maximos' => 5,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('configuraciones_fiscales', [
            'establecimiento_id' => $otro->getKey(),
            'cliente_key' => 'est-ui',
            'intentos_maximos' => 5,
        ]);

        $rawSecret = DB::table('configuraciones_fiscales')->value('cliente_secret');
        $this->assertNotSame('super-secreto', $rawSecret);
        $this->assertSame('super-secreto', ConfiguracionFiscal::where('cliente_key', 'est-ui')->first()->cliente_secret);
    }

    public function test_ventas_admin_accede_y_ve_la_venta(): void
    {
        $venta = $this->syncVenta();

        $this->actingAs($this->admin)
            ->get(ManageVentasFiscales::getUrl())
            ->assertOk()
            ->assertSee($venta->referencia);
    }

    public function test_ventas_cajero_no_accede(): void
    {
        $this->actingAs($this->cashier)
            ->get(ManageVentasFiscales::getUrl())
            ->assertForbidden();
    }

    public function test_documentos_admin_accede_a_la_pagina(): void
    {
        $venta = $this->syncVenta();
        DocumentoFiscal::create([
            'pedido_id' => $venta->pedido_id,
            'venta_fiscal_pos_id' => $venta->getKey(),
            'tipo_documento' => TipoDocumento::FACTURA,
            'estado' => EstadoDocumentoFiscal::PENDIENTE,
            'datos_solicitante' => ['nombre' => 'Cliente Final'],
            'solicitado_at' => now(),
            'expires_at' => now()->addHours(48),
        ]);

        $this->actingAs($this->admin)
            ->get(ManageDocumentosFiscales::getUrl())
            ->assertOk()
            ->assertSee('Cliente Final');
    }

    public function test_documentos_cajero_no_accede(): void
    {
        $this->actingAs($this->cashier)
            ->get(ManageDocumentosFiscales::getUrl())
            ->assertForbidden();
    }

    public function test_reintentar_action_reencola_la_venta_fallida(): void
    {
        $venta = $this->syncVenta();
        $venta->update(['estado' => EstadoVentaFiscal::ENVIO_FALLIDO->value]);
        $venta->cola->update(['estado' => EstadoColaVentaFiscal::FALLIDO->value, 'ultimo_error' => 'boom']);

        Queue::fake();
        Http::fake();

        $this->actingAs($this->admin);

        Livewire::test(ManageVentasFiscales::class)
            ->callTableAction('reintentar', $venta)
            ->assertHasNoActionErrors();

        $this->assertSame(EstadoColaVentaFiscal::PENDIENTE->value, $venta->cola->fresh()->estado->value);
        $this->assertSame(EstadoVentaFiscal::NO->value, $venta->fresh()->estado->value);
        Queue::assertPushed(EnviarVentasFiscalesJob::class);
    }

    public function test_solicitar_documento_action_crea_la_solicitud(): void
    {
        $venta = $this->syncVenta();

        $this->actingAs($this->admin);

        Livewire::test(ManageVentasFiscales::class)
            ->callTableAction('solicitar_documento', $venta, data: [
                'tipo_documento' => TipoDocumento::CCF->value,
                'receptor_nombre' => 'Cliente Final',
                'receptor_documento' => '06143402-1',
                'receptor_tipo_documento' => 'NIT',
            ])
            ->assertHasNoActionErrors();

        $documento = DocumentoFiscal::where('venta_fiscal_pos_id', $venta->getKey())->firstOrFail();
        $this->assertSame(TipoDocumento::CCF, $documento->tipo_documento);
        $this->assertSame(EstadoDocumentoFiscal::PENDIENTE, $documento->estado);
        $this->assertSame('Cliente Final', $documento->datos_solicitante['nombre']);
        $this->assertTrue($documento->isSolicitable());
    }

    public function test_solicitar_documento_action_no_disponible_para_venta_no_sincronizada(): void
    {
        $venta = $this->syncVenta();
        $venta->update(['estado' => EstadoVentaFiscal::ENVIO_FALLIDO->value]);

        $this->actingAs($this->admin);

        Livewire::test(ManageVentasFiscales::class)
            ->assertTableActionHidden('solicitar_documento', $venta);
    }

    public function test_resources_estan_en_el_grupo_fiscal(): void
    {
        $this->assertSame('Fiscal', VentaFiscalPosResource::getNavigationGroup());
        $this->assertSame('Fiscal', ConfiguracionFiscalResource::getNavigationGroup());
        $this->assertSame('Fiscal', DocumentoFiscalResource::getNavigationGroup());
    }
}
