<?php

namespace Tests\Feature\Portal;

use App\Contracts\FiscalGatewayInterface;
use App\Enums\DisponibilidadProducto;
use App\Enums\EstadoComercialPedido;
use App\Enums\EstadoDocumentoFiscal;
use App\Enums\EstadoLineaPedido;
use App\Enums\OrigenPedido;
use App\Enums\TipoDocumento;
use App\Enums\TipoPedido;
use App\Models\Categoria;
use App\Models\DocumentoFiscal;
use App\Models\Establecimiento;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use App\Services\Portal\PortalFiscalService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalQrTest extends TestCase
{
    use RefreshDatabase;

    private Establecimiento $establecimiento;
    private Pedido $pedido;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermissionsSeeder::class);

        $this->user = User::factory()->create([
            'usuario' => 'admin_test',
            'password' => 'secret123',
        ]);
        $this->user->assignRole('administrador');

        $this->establecimiento = Establecimiento::create([
            'nombre' => 'Boomwalos Central',
            'direccion' => 'Calle Principal #123',
        ]);

        $categoria = Categoria::create([
            'nombre' => 'Pupusas',
            'establecimiento_id' => $this->establecimiento->getKey(),
        ]);

        $producto = Producto::create([
            'nombre' => 'Pupusa Suprema',
            'precio' => 2.50,
            'categoria_id' => $categoria->getKey(),
            'establecimiento_id' => $this->establecimiento->getKey(),
            'disponibilidad' => DisponibilidadProducto::DISPONIBLE,
        ]);

        $this->pedido = Pedido::create([
            'numero_seguimiento' => 'TRACK-TEST-100',
            'codigo_corto' => 100,
            'fecha_codigo' => now()->toDateString(),
            'tipo_pedido' => TipoPedido::MESA,
            'establecimiento_id' => $this->establecimiento->getKey(),
            'usuario_id' => $this->user->getKey(),
            'origen_pedido' => OrigenPedido::CAJA,
            'estado_comercial' => EstadoComercialPedido::COBRADO,
        ]);

        $this->pedido->detalles()->create([
            'producto_id' => $producto->getKey(),
            'cantidad' => 2,
            'precio_unitario' => 2.50,
            'estado_linea' => EstadoLineaPedido::ACTIVA,
        ]);

        Pago::create([
            'pedido_id' => $this->pedido->getKey(),
            'monto_recibido' => 5.00,
            'cambio_devuelto' => 0.00,
            'metodo_pago' => \App\Enums\MetodoPago::EFECTIVO,
            'fecha_pago' => now(),
        ]);
    }

    public function test_consultar_orden_returns_404_when_not_found(): void
    {
        $response = $this->getJson('/api/v1/portal-qr/orden/TRACK-INEXISTENTE');

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_consultar_orden_returns_valid_order_data_by_tracking_or_codigo_corto(): void
    {
        $responseTracking = $this->getJson('/api/v1/portal-qr/orden/TRACK-TEST-100');
        $responseTracking->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.orden.tracking_number', 'TRACK-TEST-100')
            ->assertJsonPath('data.orden.total', '5.00')
            ->assertJsonCount(1, 'data.orden.items');

        $responseCodigo = $this->getJson('/api/v1/portal-qr/orden/100');
        $responseCodigo->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.orden.tracking_number', 'TRACK-TEST-100');
    }

    public function test_solicitar_in_manual_mode_creates_pending_request(): void
    {
        app(PortalFiscalService::class)->guardarModoEmision(PortalFiscalService::MODO_MANUAL, $this->establecimiento->getKey());

        $payload = [
            'trackingPOS' => 'TRACK-TEST-100',
            'tipoDTE' => '01',
            'nombre' => 'Juan Pérez',
            'email' => 'juan@example.com',
            'telefono' => '7000-0000',
            'dui' => '01234567-8',
        ];

        $response = $this->postJson('/api/v1/portal-qr/solicitar', $payload);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('estado', 'PENDIENTE');

        $this->assertDatabaseHas('documento_fiscales', [
            'pedido_id' => $this->pedido->getKey(),
            'tipo_documento' => TipoDocumento::FACTURA->value,
            'estado' => EstadoDocumentoFiscal::PENDIENTE->value,
        ]);
    }

    public function test_solicitar_in_automatic_mode_emits_dte_immediately(): void
    {
        app(PortalFiscalService::class)->guardarModoEmision(PortalFiscalService::MODO_AUTOMATICO, $this->establecimiento->getKey());

        $payload = [
            'trackingPOS' => 'TRACK-TEST-100',
            'tipoDTE' => '01',
            'nombre' => 'Empresa S.A. de C.V.',
            'email' => 'facturacion@empresa.com',
            'telefono' => '2222-2222',
            'nit' => '0614-010101-101-1',
        ];

        $response = $this->postJson('/api/v1/portal-qr/solicitar', $payload);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('estado', 'EMITIDO')
            ->assertJsonStructure(['dte' => ['codigo_generacion', 'sello_recepcion']]);

        $this->assertDatabaseHas('documento_fiscales', [
            'pedido_id' => $this->pedido->getKey(),
            'estado' => EstadoDocumentoFiscal::EMITIDO->value,
        ]);
    }

    public function test_solicitar_in_hybrid_mode_handles_factura_and_ccf_differently(): void
    {
        app(PortalFiscalService::class)->guardarModoEmision(PortalFiscalService::MODO_HIBRIDO, $this->establecimiento->getKey());

        // CCF (03) -> PENDIENTE (Validación manual)
        $payloadCCF = [
            'trackingPOS' => 'TRACK-TEST-100',
            'tipoDTE' => '03',
            'nombre' => 'Constructora Demo',
            'email' => 'info@constructora.com',
            'telefono' => '2233-4455',
            'nit' => '0614-010101-102-2',
            'nrc' => '123456-7',
            'giro' => 'Construcción',
            'direccion' => 'San Salvador',
            'departamento' => 'San Salvador',
            'municipio' => 'San Salvador Centro',
        ];

        $responseCCF = $this->postJson('/api/v1/portal-qr/solicitar', $payloadCCF);
        $responseCCF->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('estado', 'PENDIENTE');

        $this->assertDatabaseHas('documento_fiscales', [
            'pedido_id' => $this->pedido->getKey(),
            'tipo_documento' => TipoDocumento::CCF->value,
            'estado' => EstadoDocumentoFiscal::PENDIENTE->value,
        ]);
    }

    public function test_solicitar_returns_existing_data_if_already_emitted(): void
    {
        DocumentoFiscal::create([
            'pedido_id' => $this->pedido->getKey(),
            'tipo_documento' => TipoDocumento::FACTURA,
            'estado' => EstadoDocumentoFiscal::EMITIDO,
            'codigo_generacion' => 'GEN-12345',
            'sello_recepcion' => 'SELLO-12345',
            'numero_control' => 'DTE-01-001',
        ]);

        $payload = [
            'trackingPOS' => 'TRACK-TEST-100',
            'tipoDTE' => '01',
            'nombre' => 'Cliente Test',
            'email' => 'cliente@test.com',
            'telefono' => '7111-2222',
        ];

        $response = $this->postJson('/api/v1/portal-qr/solicitar', $payload);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('estado', 'EMITIDO')
            ->assertJsonPath('dte.codigo_generacion', 'GEN-12345');
    }
}
