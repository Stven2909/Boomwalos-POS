<?php

namespace Tests\Feature\Portal;

use App\Enums\EstadoDocumentoFiscal;
use App\Enums\EstadoVentaFiscal;
use App\Enums\TipoDocumento;
use App\Enums\TipoPedido;
use App\Models\Categoria;
use App\Models\ConfiguracionFiscal;
use App\Models\DocumentoFiscal;
use App\Models\Establecimiento;
use App\Models\Pedido;
use App\Models\PortalSnapshotOutbox;
use App\Models\Producto;
use App\Models\SesionCaja;
use App\Models\TrabajoImpresion;
use App\Models\User;
use App\Models\VentaFiscalPos;
use App\Services\PedidoService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Paso 7: reconciliación del estado y los identificadores del DTE desde
 * ApiFact hacia los registros locales del POS. Nunca reimprime.
 */
class PortalReconciliacionTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Establecimiento $establishment;

    private Producto $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesPermissionsSeeder::class);

        $this->cashier = User::factory()->create([
            'usuario' => '51',
            'password' => '1234',
        ]);
        $this->cashier->assignRole('cajero');

        $this->establishment = Establecimiento::create([
            'nombre' => 'Pupusería Demo',
            'direccion' => 'Dirección de prueba',
        ]);

        $category = Categoria::create(['nombre' => 'Bebidas']);
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

        config(['fiscal.url' => 'https://apifact.test']);

        ConfiguracionFiscal::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'razon_social' => 'Pupusería Demo',
            'codigo_establecimiento' => 'M001',
            'codigo_punto_venta' => 'P001',
            'fiscal_habilitada' => true,
            'cliente_key' => 'est-test',
            'cliente_secret' => 'secreto-reconciliacion',
            'intentos_maximos' => 3,
        ]);
    }

    public function test_documento_pendiente_pasa_a_emitido_con_los_identificadores_del_dte(): void
    {
        $pedido = $this->newOrder();
        $this->sincronizar($pedido);

        $documento = DocumentoFiscal::create([
            'pedido_id' => $pedido->getKey(),
            'tipo_documento' => TipoDocumento::FACTURA,
            'estado' => EstadoDocumentoFiscal::PENDIENTE,
        ]);

        $capturado = [];

        Http::fake(function (Request $request) use (&$capturado) {
            $capturado['url'] = $request->url();
            $capturado['headers'] = $request->headers();

            return Http::response([
                'success' => true,
                'data' => $this->orden('EMITIDA', [
                    'estado_fiscal' => 'SEALED',
                    'estado_solicitud' => 'EMITIDO',
                    'tipo_documento' => 'FACTURA',
                ]),
            ]);
        });

        $this->artisan('portal:reconciliar')->assertSuccessful();

        $documento->refresh();

        $this->assertSame(EstadoDocumentoFiscal::EMITIDO, $documento->estado);
        $this->assertSame('11111111-2222-3333-4444-555555555555', $documento->codigo_generacion);
        $this->assertSame('DTE-01-M001P001-000000000000123', $documento->numero_control);
        $this->assertSame('SELLO-DE-PRUEBA-2026', $documento->sello_recepcion);

        $this->assertStringContainsString('/api/v1/portal/pedidos/'.$pedido->numero_seguimiento, $capturado['url']);
        $this->assertArrayHasKey('x-signature', array_change_key_case($capturado['headers'], CASE_LOWER));

        // La reconciliación no reimprime: el DTE final solo se imprime en
        // contingencia o recuperación.
        $this->assertSame(0, TrabajoImpresion::query()->count());
    }

    public function test_en_proceso_conserva_pendiente_y_guarda_el_codigo_sin_sello(): void
    {
        $pedido = $this->newOrder();
        $this->sincronizar($pedido);

        $documento = DocumentoFiscal::create([
            'pedido_id' => $pedido->getKey(),
            'tipo_documento' => TipoDocumento::FACTURA,
            'estado' => EstadoDocumentoFiscal::PENDIENTE,
        ]);

        Http::fake(['*' => Http::response([
            'success' => true,
            'data' => $this->orden('EN_PROCESO', [
                'estado_fiscal' => 'NO_SEAL',
                'estado_solicitud' => 'PENDIENTE',
                'tipo_documento' => 'FACTURA',
                'sello_recepcion' => null,
            ]),
        ])]);

        $this->artisan('portal:reconciliar')->assertSuccessful();

        $documento->refresh();

        $this->assertSame(EstadoDocumentoFiscal::PENDIENTE, $documento->estado);
        $this->assertSame('11111111-2222-3333-4444-555555555555', $documento->codigo_generacion);
        $this->assertNull($documento->sello_recepcion);
    }

    public function test_rechazo_marca_el_documento_como_rechazado(): void
    {
        $pedido = $this->newOrder();
        $this->sincronizar($pedido);

        $documento = DocumentoFiscal::create([
            'pedido_id' => $pedido->getKey(),
            'tipo_documento' => TipoDocumento::FACTURA,
            'estado' => EstadoDocumentoFiscal::PENDIENTE,
        ]);

        Http::fake(['*' => Http::response([
            'success' => true,
            'data' => $this->orden('RECHAZADA', [
                'estado_fiscal' => 'REJECTED',
                'estado_solicitud' => 'RECHAZADO',
                'tipo_documento' => 'FACTURA',
                'sello_recepcion' => null,
            ]),
        ])]);

        $this->artisan('portal:reconciliar')->assertSuccessful();

        $this->assertSame(EstadoDocumentoFiscal::RECHAZADO, $documento->fresh()->estado);
        $this->assertSame(0, TrabajoImpresion::query()->count());
    }

    public function test_un_documento_ya_emitido_no_retrocede(): void
    {
        $pedido = $this->newOrder();
        $this->sincronizar($pedido);

        $documento = DocumentoFiscal::create([
            'pedido_id' => $pedido->getKey(),
            'tipo_documento' => TipoDocumento::FACTURA,
            'estado' => EstadoDocumentoFiscal::EMITIDO,
            'codigo_generacion' => '11111111-2222-3333-4444-555555555555',
            'numero_control' => 'DTE-01-M001P001-000000000000123',
            'sello_recepcion' => 'SELLO-DE-PRUEBA-2026',
        ]);

        Http::fake(['*' => Http::response([
            'success' => true,
            'data' => $this->orden('RECHAZADA', [
                'estado_fiscal' => 'REJECTED',
                'estado_solicitud' => 'RECHAZADO',
                'tipo_documento' => 'FACTURA',
            ]),
        ])]);

        $this->artisan('portal:reconciliar')->assertSuccessful();

        $this->assertSame(EstadoDocumentoFiscal::EMITIDO, $documento->fresh()->estado);
    }

    public function test_venta_fiscal_de_tarjeta_recibe_identificadores_y_estado(): void
    {
        $pedido = $this->newOrder();
        $this->sincronizar($pedido);

        $venta = VentaFiscalPos::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'pedido_id' => $pedido->getKey(),
            'referencia' => $pedido->numero_seguimiento,
            'monto_total' => 4,
            'metodo_pago' => 'TARJETA',
            'estado' => EstadoVentaFiscal::NO,
        ]);

        Http::fake(['*' => Http::response([
            'success' => true,
            'data' => $this->orden('EMITIDA', [
                'estado_fiscal' => 'SEALED',
                'estado_solicitud' => 'EMITIDO',
                'tipo_documento' => 'FACTURA',
            ]),
        ])]);

        $this->artisan('portal:reconciliar')->assertSuccessful();

        $venta->refresh();

        $this->assertSame(EstadoVentaFiscal::SINCRONIZADO, $venta->estado);
        $this->assertSame('11111111-2222-3333-4444-555555555555', $venta->codigo_generacion);
        $this->assertSame('DTE-01-M001P001-000000000000123', $venta->numero_control);
        $this->assertSame('SELLO-DE-PRUEBA-2026', $venta->sello_recepcion);
        $this->assertNotNull($venta->sincronizado_at);
    }

    public function test_un_fallo_de_red_no_cambia_el_documento_ni_falla_el_comando(): void
    {
        $pedido = $this->newOrder();
        $this->sincronizar($pedido);

        $documento = DocumentoFiscal::create([
            'pedido_id' => $pedido->getKey(),
            'tipo_documento' => TipoDocumento::FACTURA,
            'estado' => EstadoDocumentoFiscal::PENDIENTE,
        ]);

        Http::fake(['*' => Http::response('ApiFact no disponible', 503)]);

        $this->artisan('portal:reconciliar')->assertSuccessful();

        $documento->refresh();

        $this->assertSame(EstadoDocumentoFiscal::PENDIENTE, $documento->estado);
        $this->assertNull($documento->codigo_generacion);
    }

    public function test_no_consulta_ordenes_cuyo_snapshot_no_esta_sincronizado(): void
    {
        $pedido = $this->newOrder();
        $this->sincronizar($pedido, estado: 'EN_COLA');

        DocumentoFiscal::create([
            'pedido_id' => $pedido->getKey(),
            'tipo_documento' => TipoDocumento::FACTURA,
            'estado' => EstadoDocumentoFiscal::PENDIENTE,
        ]);

        Http::fake();

        $this->artisan('portal:reconciliar')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_respeta_la_ventana_de_reconciliacion(): void
    {
        $pedido = $this->newOrder();
        $this->sincronizar($pedido, sincronizadoAt: now()->subDays(30));

        DocumentoFiscal::create([
            'pedido_id' => $pedido->getKey(),
            'tipo_documento' => TipoDocumento::FACTURA,
            'estado' => EstadoDocumentoFiscal::PENDIENTE,
        ]);

        Http::fake();

        $this->artisan('portal:reconciliar')->assertSuccessful();

        Http::assertNothingSent();
    }

    private function newOrder(): Pedido
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier);
        $service->addProduct($pedido, $this->product, $this->cashier);

        return $pedido;
    }

    private function sincronizar(
        Pedido $pedido,
        string $estado = 'SINCRONIZADO',
        mixed $sincronizadoAt = null,
    ): PortalSnapshotOutbox {
        return PortalSnapshotOutbox::create([
            'pedido_id' => $pedido->getKey(),
            'establecimiento_id' => $this->establishment->getKey(),
            'tracking' => $pedido->numero_seguimiento,
            'payload' => ['tracking' => $pedido->numero_seguimiento],
            'estado' => $estado,
            'sincronizado_at' => $sincronizadoAt ?? now(),
        ]);
    }

    private function orden(string $estadoVisible, array $extra = []): array
    {
        return array_merge([
            'tracking_number' => 'POS-261111183000-AAAA',
            'estado_visible' => $estadoVisible,
            'estado_fiscal' => null,
            'estado_solicitud' => 'SIN_SOLICITUD',
            'tipo_documento' => null,
            'codigo_generacion' => '11111111-2222-3333-4444-555555555555',
            'numero_control' => 'DTE-01-M001P001-000000000000123',
            'sello_recepcion' => 'SELLO-DE-PRUEBA-2026',
        ], $extra);
    }
}
