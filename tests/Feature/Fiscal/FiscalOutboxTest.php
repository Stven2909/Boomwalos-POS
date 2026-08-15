<?php

namespace Tests\Feature\Fiscal;

use App\Application\Fiscal\FiscalOutboxService;
use App\Application\Fiscal\HmacSigner;
use App\Enums\EstadoComercialPedido;
use App\Enums\EstadoDocumentoFiscal;
use App\Enums\EstadoMesa;
use App\Enums\EstadoVentaFiscal;
use App\Enums\EstadoWebhookPos;
use App\Enums\MetodoPago;
use App\Enums\TipoDocumento;
use App\Enums\TipoPedido;
use App\Enums\ZonaMesa;
use App\Jobs\EnviarVentasFiscalesJob;
use App\Models\Categoria;
use App\Models\ConfiguracionFiscal;
use App\Models\DocumentoFiscal;
use App\Models\Establecimiento;
use App\Models\FiscalSyncState;
use App\Models\Impresora;
use App\Models\Mesa;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\SesionCaja;
use App\Models\User;
use App\Models\VentaFiscalPos;
use App\Services\CobroService;
use App\Services\PedidoService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class FiscalOutboxTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Establecimiento $establishment;

    private Mesa $table;

    private Producto $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesPermissionsSeeder::class);

        $this->cashier = User::factory()->create([
            'usuario' => '21',
            'password' => '1234',
        ]);
        $this->cashier->assignRole('cajero');

        $this->establishment = Establecimiento::create([
            'nombre' => 'Los Boomwalos',
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
    }

    private function enableFiscalConfig(bool $habilitada = true): ConfiguracionFiscal
    {
        return ConfiguracionFiscal::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'fiscal_habilitada' => $habilitada,
            'cliente_key' => 'est-test',
            'cliente_secret' => (string) config('fiscal.mock.secret'),
            'intentos_maximos' => 3,
        ]);
    }

    private function chargedPedido(): array
    {
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

        return [$pedido, $pago];
    }

    private function postWebhook(array $datos): TestResponse
    {
        $content = json_encode($datos);

        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_FISCAL_KEY' => 'est-test',
            'HTTP_X_FISCAL_TIMESTAMP' => (string) time(),
            'HTTP_X_FISCAL_HMAC' => HmacSigner::sign((string) $content, (string) config('fiscal.mock.secret')),
        ];

        return $this->call('POST', '/api/fiscal/v1/webhooks', [], [], [], $server, $content);
    }

    public function test_cobro_sin_config_fiscal_no_registra_venta(): void
    {
        Queue::fake();
        [$pedido, $pago] = $this->chargedPedido();

        $this->assertDatabaseCount('ventas_fiscales_pos', 0);
        $this->assertDatabaseCount('cola_ventas_fiscales', 0);
    }

    public function test_cobro_con_config_deshabilitada_no_registra_venta(): void
    {
        Queue::fake();
        $this->enableFiscalConfig(false);

        [$pedido, $pago] = $this->chargedPedido();

        $this->assertDatabaseCount('ventas_fiscales_pos', 0);
    }

    public function test_cobro_con_fiscal_habilitada_registra_venta_y_cola(): void
    {
        Queue::fake();
        $this->enableFiscalConfig();

        [$pedido, $pago] = $this->chargedPedido();

        Queue::assertPushed(EnviarVentasFiscalesJob::class);

        $venta = VentaFiscalPos::where('pago_id', $pago->getKey())->first();
        $this->assertNotNull($venta);
        $this->assertSame(EstadoVentaFiscal::NO->value, $venta->estado->value);
        $this->assertSame('4.00', (string) $venta->monto_total);
        $this->assertSame(MetodoPago::EFECTIVO->value, $venta->metodo_pago);
        $this->assertNull($venta->fiscal_sale_id);

        $cola = $venta->cola;
        $this->assertNotNull($cola);
        $this->assertSame('PENDIENTE', $cola->estado->value);
        $this->assertSame(
            'v-' . $this->establishment->getKey() . '-' . $pedido->getKey() . '-' . $pago->getKey(),
            $cola->clave_reintento,
        );
        $this->assertSame('4.00', $cola->payload_envio['monto_total']);
        $this->assertSame('EFECTIVO', $cola->payload_envio['metodo_pago']);
    }

    public function test_job_sincroniza_venta_al_recibir_202(): void
    {
        Queue::fake();
        $this->enableFiscalConfig();

        [, $pago] = $this->chargedPedido();
        $venta = VentaFiscalPos::where('pago_id', $pago->getKey())->first();

        Http::fake([
            '*' => Http::response([
                'fiscal_sale_id' => 'MOCK-F2-202',
                'estado' => 'RECIBIDA',
                'qr_url' => null,
            ], 202),
        ]);

        (new EnviarVentasFiscalesJob($venta->getKey()))->handle(app(FiscalOutboxService::class));

        $venta->refresh();
        $this->assertSame(EstadoVentaFiscal::SINCRONIZADO->value, $venta->estado->value);
        $this->assertSame('MOCK-F2-202', $venta->fiscal_sale_id);
        $this->assertNull($venta->receptor);
        $this->assertNotNull($venta->sincronizado_at);
        $this->assertSame('ENVIADO', $venta->cola->fresh()->estado->value);
        $this->assertSame(1, $venta->cola->fresh()->intentos);
    }

    public function test_payload_incluye_receptor_y_se_borra_al_sincronizar(): void
    {
        Queue::fake();
        $this->enableFiscalConfig();

        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::MESA, $this->cashier, $this->table->getKey());
        $service->addProduct($pedido, $this->product, $this->cashier);
        $service->sendPendingBatch($pedido, $this->cashier);

        $datosSolicitante = [
            'nombre' => 'Receptor de Prueba',
            'documento' => '06143402-1',
            'tipo_documento' => 'NIT',
        ];

        DocumentoFiscal::create([
            'pedido_id' => $pedido->getKey(),
            'tipo_documento' => TipoDocumento::FACTURA->value,
            'estado' => EstadoDocumentoFiscal::PENDIENTE->value,
            'datos_solicitante' => $datosSolicitante,
        ]);

        $pago = app(CobroService::class)->charge(
            $pedido,
            MetodoPago::EFECTIVO,
            '10.00',
            $this->cashier,
        );

        $venta = VentaFiscalPos::where('pago_id', $pago->getKey())->first();
        $this->assertSame($datosSolicitante, $venta->receptor);
        $this->assertSame($datosSolicitante, $venta->cola->payload_envio['receptor']);

        Http::fake(['*' => Http::response(['fiscal_sale_id' => 'MOCK-F2-R', 'estado' => 'RECIBIDA'], 202)]);

        (new EnviarVentasFiscalesJob($venta->getKey()))->handle(app(FiscalOutboxService::class));

        $venta->refresh();
        $this->assertNull($venta->receptor);
        $this->assertSame($datosSolicitante, $venta->cola->fresh()->payload_envio['receptor']);
        $this->assertNull(DocumentoFiscal::where('pedido_id', $pedido->getKey())->first()->fresh()->datos_solicitante);
    }

    public function test_fallo_http_marca_venta_y_cola_fallidas(): void
    {
        Queue::fake();
        $this->enableFiscalConfig();

        [, $pago] = $this->chargedPedido();
        $venta = VentaFiscalPos::where('pago_id', $pago->getKey())->first();

        Http::fake(['*' => Http::response('error interno', 500)]);

        (new EnviarVentasFiscalesJob($venta->getKey()))->handle(app(FiscalOutboxService::class));

        $venta->refresh();
        $this->assertSame(EstadoVentaFiscal::ENVIO_FALLIDO->value, $venta->estado->value);
        $this->assertNull($venta->fiscal_sale_id);
        $this->assertSame('FALLIDO', $venta->cola->fresh()->estado->value);
        $this->assertSame(1, $venta->cola->fresh()->intentos);
        $this->assertNotNull($venta->cola->fresh()->ultimo_error);
    }

    public function test_reintento_manual_reusa_clave_y_payload_y_se_recupera(): void
    {
        Queue::fake();
        $this->enableFiscalConfig();

        [, $pago] = $this->chargedPedido();
        $venta = VentaFiscalPos::where('pago_id', $pago->getKey())->first();

        $llamadas = 0;

        Http::fake(function (Request $request) use (&$llamadas) {
            $llamadas++;

            if ($llamadas === 1) {
                return Http::response('error interno', 500);
            }

            return Http::response(['fiscal_sale_id' => 'MOCK-F2-RT', 'estado' => 'RECIBIDA'], 202);
        });

        (new EnviarVentasFiscalesJob($venta->getKey()))->handle(app(FiscalOutboxService::class));

        $venta->refresh();
        $cola = $venta->cola->fresh();
        $claveOriginal = $cola->clave_reintento;
        $payloadOriginal = $cola->payload_envio;

        app(FiscalOutboxService::class)->reintentar($cola);

        $cola->refresh();
        $this->assertSame('PENDIENTE', $cola->estado->value);
        $this->assertSame($claveOriginal, $cola->clave_reintento);
        $this->assertSame($payloadOriginal, $cola->payload_envio);
        $this->assertSame(EstadoVentaFiscal::NO->value, $venta->fresh()->estado->value);

        (new EnviarVentasFiscalesJob($venta->getKey()))->handle(app(FiscalOutboxService::class));

        $venta->refresh();
        $this->assertSame(EstadoVentaFiscal::SINCRONIZADO->value, $venta->estado->value);
        $this->assertSame('MOCK-F2-RT', $venta->fiscal_sale_id);
        $this->assertSame('ENVIADO', $venta->cola->fresh()->estado->value);
    }

    public function test_webhook_dte_emitido_en_orden_marca_documento_emitido(): void
    {
        [$pedido, $pago] = $this->chargedPedido();

        $venta = VentaFiscalPos::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'pedido_id' => $pedido->getKey(),
            'pago_id' => $pago->getKey(),
            'referencia' => $pedido->numero_seguimiento,
            'monto_total' => '4.00',
            'metodo_pago' => MetodoPago::EFECTIVO->value,
            'fiscal_sale_id' => 'MOCK-WH-1',
            'estado' => EstadoVentaFiscal::SINCRONIZADO->value,
            'sincronizado_at' => now(),
        ]);

        $documento = DocumentoFiscal::create([
            'pedido_id' => $pedido->getKey(),
            'venta_fiscal_pos_id' => $venta->getKey(),
            'tipo_documento' => TipoDocumento::FACTURA->value,
            'estado' => EstadoDocumentoFiscal::PENDIENTE->value,
        ]);

        $this->postWebhook([
            'secuencia' => 1,
            'tipo' => 'DTE_EMITIDO',
            'fiscal_sale_id' => 'MOCK-WH-1',
            'payload' => [
                'codigo_generacion' => 'AAA-2026-00000001',
                'numero_control' => 'DTE-01-00000001',
                'sello_recepcion' => 'SELLO-XYZ',
            ],
        ])
            ->assertStatus(202)
            ->assertJsonPath('estado', 'PROCESADO');

        $documento->refresh();
        $this->assertSame(EstadoDocumentoFiscal::EMITIDO->value, $documento->estado->value);
        $this->assertSame('AAA-2026-00000001', $documento->codigo_generacion);
        $this->assertSame('DTE-01-00000001', $documento->numero_control);
        $this->assertSame('SELLO-XYZ', $documento->sello_recepcion);

        $this->assertDatabaseHas('webhook_events_pos', [
            'secuencia' => 1,
            'estado' => EstadoWebhookPos::PROCESADO->value,
        ]);
        $this->assertSame(1, FiscalSyncState::where('establecimiento_id', $this->establishment->getKey())->value('ultima_secuencia_webhook'));
    }

    public function test_webhook_fuera_de_orden_queda_pendiente_hasta_reconciliar(): void
    {
        [$pedido, $pago] = $this->chargedPedido();

        $venta = VentaFiscalPos::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'pedido_id' => $pedido->getKey(),
            'pago_id' => $pago->getKey(),
            'referencia' => $pedido->numero_seguimiento,
            'monto_total' => '4.00',
            'metodo_pago' => MetodoPago::EFECTIVO->value,
            'fiscal_sale_id' => 'MOCK-WH-2',
            'estado' => EstadoVentaFiscal::SINCRONIZADO->value,
            'sincronizado_at' => now(),
        ]);

        DocumentoFiscal::create([
            'pedido_id' => $pedido->getKey(),
            'venta_fiscal_pos_id' => $venta->getKey(),
            'tipo_documento' => TipoDocumento::FACTURA->value,
            'estado' => EstadoDocumentoFiscal::PENDIENTE->value,
        ]);

        $this->postWebhook([
            'secuencia' => 2,
            'tipo' => 'DTE_EMITIDO',
            'fiscal_sale_id' => 'MOCK-WH-2',
            'payload' => ['codigo_generacion' => 'AAA-2026-00000002'],
        ])->assertStatus(202)->assertJsonPath('estado', 'PENDIENTE');

        $this->assertDatabaseHas('webhook_events_pos', [
            'secuencia' => 2,
            'estado' => EstadoWebhookPos::PENDIENTE->value,
        ]);
        $this->assertSame(0, FiscalSyncState::where('establecimiento_id', $this->establishment->getKey())->value('ultima_secuencia_webhook'));

        $this->postWebhook([
            'secuencia' => 1,
            'tipo' => 'DTE_EMITIDO',
            'fiscal_sale_id' => 'MOCK-WH-2',
            'payload' => ['codigo_generacion' => 'AAA-2026-00000001'],
        ])->assertStatus(202)->assertJsonPath('estado', 'PROCESADO');

        $this->assertDatabaseHas('webhook_events_pos', [
            'secuencia' => 1,
            'estado' => EstadoWebhookPos::PROCESADO->value,
        ]);
        $this->assertDatabaseHas('webhook_events_pos', [
            'secuencia' => 2,
            'estado' => EstadoWebhookPos::PROCESADO->value,
        ]);
        $this->assertSame(2, FiscalSyncState::where('establecimiento_id', $this->establishment->getKey())->value('ultima_secuencia_webhook'));
    }

    public function test_webhook_con_venta_desconocida_se_almacena_sin_reconciliar(): void
    {
        $this->postWebhook([
            'secuencia' => 1,
            'tipo' => 'DTE_EMITIDO',
            'fiscal_sale_id' => 'MOCK-DESCONOCIDO',
            'payload' => [],
        ])->assertStatus(202)->assertJsonPath('estado', 'PENDIENTE');

        $this->assertDatabaseCount('webhook_events_pos', 1);
        $this->assertDatabaseHas('webhook_events_pos', [
            'venta_fiscal_pos_id' => null,
            'estado' => EstadoWebhookPos::PENDIENTE->value,
        ]);
        $this->assertDatabaseCount('fiscal_sync_states', 0);
    }

    public function test_cliente_envia_firma_hmac_y_cuerpo_exacto(): void
    {
        $config = $this->enableFiscalConfig();

        $pedido = app(PedidoService::class)->startOrder(TipoPedido::MESA, $this->cashier, $this->table->getKey());

        $venta = VentaFiscalPos::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'pedido_id' => $pedido->getKey(),
            'pago_id' => null,
            'referencia' => $pedido->numero_seguimiento,
            'monto_total' => '4.00',
            'metodo_pago' => MetodoPago::EFECTIVO->value,
            'estado' => EstadoVentaFiscal::NO->value,
        ]);

        $payload = [
            'clave_reintento' => 'v-1-0-0',
            'referencia' => $pedido->numero_seguimiento,
            'fecha_emision' => now()->toIso8601String(),
            'monto_total' => '4.00',
            'metodo_pago' => 'EFECTIVO',
        ];

        $venta->cola()->create([
            'clave_reintento' => 'v-1-0-0',
            'payload_envio' => $payload,
            'estado' => 'PENDIENTE',
        ]);

        $capturado = [];

        Http::fake(function (Request $request) use (&$capturado) {
            $capturado = $request->headers();
            $capturado['_cuerpo'] = $request->body();

            return Http::response(['fiscal_sale_id' => 'MOCK-CL-1', 'estado' => 'RECIBIDA'], 202);
        });

        app(FiscalOutboxService::class)->enviarPendientes($venta->getKey());

        $this->assertSame($config->cliente_key, $capturado['X-Fiscal-Key'][0]);
        $this->assertNotNull($capturado['X-Fiscal-Timestamp'][0] ?? null);
        $this->assertNotEmpty($capturado['X-Fiscal-Hmac'][0] ?? null);
        $this->assertSame(
            'sha256=' . hash_hmac('sha256', json_encode($payload), (string) $config->cliente_secret),
            $capturado['X-Fiscal-Hmac'][0],
        );
        $this->assertSame(json_encode($payload), $capturado['_cuerpo']);
    }
}
