<?php

namespace Tests\Feature\Portal;

use App\Application\Fiscal\FiscalClient;
use App\Application\Fiscal\HmacSigner;
use App\Enums\MetodoPago;
use App\Enums\TipoPedido;
use App\Jobs\SyncPortalPedidoJob;
use App\Models\Categoria;
use App\Models\ConfiguracionFiscal;
use App\Models\Establecimiento;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\PortalSnapshotOutbox;
use App\Models\Producto;
use App\Models\SesionCaja;
use App\Models\User;
use App\Services\CobroService;
use App\Services\PedidoService;
use App\Services\Portal\PortalSnapshotRegistrar;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PortalSnapshotOutboxTest extends TestCase
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

        $this->fiscalConfig();
    }

    private function fiscalConfig(bool $habilitada = true): ConfiguracionFiscal
    {
        return ConfiguracionFiscal::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'razon_social' => 'Pupusería Demo',
            'codigo_establecimiento' => 'M001',
            'codigo_punto_venta' => 'P001',
            'fiscal_habilitada' => $habilitada,
            'cliente_key' => 'est-test',
            'cliente_secret' => 'secreto-snapshot',
            'intentos_maximos' => 3,
        ]);
    }

    private function newOrder(): Pedido
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier);
        $service->addProduct($pedido, $this->product, $this->cashier);

        return $pedido;
    }

    private function charge(MetodoPago $metodo): Pago
    {
        return app(CobroService::class)->charge(
            $this->newOrder(),
            $metodo,
            $metodo === MetodoPago::EFECTIVO ? '10.00' : null,
            $this->cashier,
            $metodo === MetodoPago::TARJETA ? ['aprobada' => true, 'referencia' => 'TEST-REF'] : null,
        );
    }

    private function outboxFor(Pago $pago): PortalSnapshotOutbox
    {
        return PortalSnapshotOutbox::query()
            ->where('pedido_id', $pago->pedido_id)
            ->firstOrFail();
    }

    public function test_efectivo_crea_snapshot_sin_venta_fiscal(): void
    {
        Queue::fake();

        $pago = $this->charge(MetodoPago::EFECTIVO);
        $outbox = $this->outboxFor($pago);

        $this->assertSame('EFECTIVO', $outbox->payload['metodo_pago']);
        $this->assertFalse($outbox->payload['dte_esperado']);
        $this->assertSame('4.00', $outbox->payload['total']);
        $this->assertSame('M001', $outbox->payload['establecimiento']);
        $this->assertSame('P001', $outbox->payload['puntoVenta']);
        $this->assertSame($pago->pedido->numero_seguimiento, $outbox->tracking);
        $this->assertSame('EN_COLA', $outbox->estado);
        $this->assertDatabaseCount('ventas_fiscales_pos', 0);

        Queue::assertPushed(SyncPortalPedidoJob::class);
    }

    public function test_tarjeta_crea_snapshot_y_venta_fiscal(): void
    {
        Queue::fake();

        $pago = $this->charge(MetodoPago::TARJETA);
        $outbox = $this->outboxFor($pago);

        $this->assertSame('TARJETA', $outbox->payload['metodo_pago']);
        $this->assertTrue($outbox->payload['dte_esperado']);
        $this->assertDatabaseHas('ventas_fiscales_pos', [
            'pago_id' => $pago->getKey(),
        ]);

        Queue::assertPushed(SyncPortalPedidoJob::class);
    }

    public function test_sin_configuracion_hmac_el_cobro_continua_sin_snapshot(): void
    {
        Queue::fake();
        ConfiguracionFiscal::query()->delete();

        $pago = $this->charge(MetodoPago::EFECTIVO);

        $this->assertDatabaseCount('portal_snapshot_outbox', 0);
        $this->assertDatabaseHas('pagos', ['id' => $pago->getKey()]);
        Queue::assertNothingPushed();
    }

    public function test_job_marca_sincronizado_al_recibir_200(): void
    {
        Queue::fake();

        $pago = $this->charge(MetodoPago::EFECTIVO);
        $outbox = $this->outboxFor($pago);

        Http::fake([
            '*' => Http::response(['tracking' => $outbox->tracking, 'recibido' => true], 200),
        ]);

        (new SyncPortalPedidoJob($outbox->getKey()))->handle(app(FiscalClient::class));

        $outbox->refresh();
        $this->assertSame('SINCRONIZADO', $outbox->estado);
        $this->assertSame(1, $outbox->intentos);
        $this->assertNotNull($outbox->sincronizado_at);
        $this->assertNull($outbox->proximo_intento_at);
        $this->assertNull($outbox->bloqueado_at);
        $this->assertNull($outbox->ultimo_error);
    }

    public function test_job_ante_fallo_deja_pendiente_con_backoff(): void
    {
        Queue::fake();

        $pago = $this->charge(MetodoPago::EFECTIVO);
        $outbox = $this->outboxFor($pago);

        Http::fake(['*' => Http::response('error interno', 500)]);

        (new SyncPortalPedidoJob($outbox->getKey()))->handle(app(FiscalClient::class));

        $outbox->refresh();
        $this->assertSame('PENDIENTE', $outbox->estado);
        $this->assertSame(1, $outbox->intentos);
        $this->assertNull($outbox->bloqueado_at);
        $this->assertNotNull($outbox->ultimo_error);
        $this->assertTrue($outbox->proximo_intento_at->isFuture());
        $this->assertTrue($outbox->proximo_intento_at->lessThanOrEqualTo(now()->addMinutes(2)));
    }

    public function test_job_es_idempotente_si_ya_esta_sincronizado(): void
    {
        Queue::fake();

        $pago = $this->charge(MetodoPago::EFECTIVO);
        $outbox = $this->outboxFor($pago);
        $outbox->forceFill(['estado' => 'SINCRONIZADO', 'sincronizado_at' => now()])->save();

        Http::fake();

        (new SyncPortalPedidoJob($outbox->getKey()))->handle(app(FiscalClient::class));

        Http::assertNothingSent();
        $this->assertSame('SINCRONIZADO', $outbox->fresh()->estado);
    }

    public function test_registrar_dos_veces_no_duplica_el_snapshot(): void
    {
        Queue::fake();

        $pago = $this->charge(MetodoPago::EFECTIVO);

        app(PortalSnapshotRegistrar::class)->encolar($pago->pedido, $pago);

        $this->assertDatabaseCount('portal_snapshot_outbox', 1);
    }

    public function test_comando_reencola_pendiente_vencido(): void
    {
        Queue::fake();

        $pago = $this->charge(MetodoPago::EFECTIVO);
        $outbox = $this->outboxFor($pago);
        $outbox->forceFill([
            'estado' => 'PENDIENTE',
            'intentos' => 1,
            'proximo_intento_at' => now()->subMinute(),
            'bloqueado_at' => null,
        ])->save();

        Queue::fake();

        $this->artisan('portal:sync-pendientes')->assertSuccessful();

        Queue::assertPushed(SyncPortalPedidoJob::class, fn (SyncPortalPedidoJob $job): bool => $job->outboxId === $outbox->getKey());

        $outbox->refresh();
        $this->assertSame('EN_COLA', $outbox->estado);
        $this->assertNotNull($outbox->bloqueado_at);
    }

    public function test_comando_ignora_pendiente_con_backoff_futuro(): void
    {
        Queue::fake();

        $pago = $this->charge(MetodoPago::EFECTIVO);
        $outbox = $this->outboxFor($pago);
        $outbox->forceFill([
            'estado' => 'PENDIENTE',
            'intentos' => 1,
            'proximo_intento_at' => now()->addMinutes(5),
            'bloqueado_at' => null,
        ])->save();

        Queue::fake();

        $this->artisan('portal:sync-pendientes')->assertSuccessful();

        Queue::assertNotPushed(SyncPortalPedidoJob::class);
        $this->assertSame('PENDIENTE', $outbox->fresh()->estado);
    }

    public function test_comando_recupera_worker_interrumpido(): void
    {
        Queue::fake();

        $pago = $this->charge(MetodoPago::EFECTIVO);
        $outbox = $this->outboxFor($pago);
        $outbox->forceFill([
            'estado' => 'PROCESANDO',
            'intentos' => 1,
            'proximo_intento_at' => now(),
            'bloqueado_at' => now()->subMinutes(11),
        ])->save();

        Queue::fake();

        $this->artisan('portal:sync-pendientes')->assertSuccessful();

        Queue::assertPushed(SyncPortalPedidoJob::class, fn (SyncPortalPedidoJob $job): bool => $job->outboxId === $outbox->getKey());
        $this->assertSame('EN_COLA', $outbox->fresh()->estado);
    }

    public function test_snapshot_se_firma_con_hmac_sobre_el_cuerpo_exacto(): void
    {
        Queue::fake();

        $pago = $this->charge(MetodoPago::EFECTIVO);
        $outbox = $this->outboxFor($pago);
        $config = ConfiguracionFiscal::query()->firstOrFail();

        $capturado = [];

        Http::fake(function (Request $request) use (&$capturado) {
            $capturado = [
                'headers' => $request->headers(),
                'body' => $request->body(),
            ];

            return Http::response(['recibido' => true], 200);
        });

        (new SyncPortalPedidoJob($outbox->getKey()))->handle(app(FiscalClient::class));

        $this->assertSame($config->cliente_key, $capturado['headers']['X-Client-Id'][0]);
        $this->assertNotEmpty($capturado['headers']['X-Timestamp'][0] ?? null);
        $this->assertNotEmpty($capturado['headers']['X-Nonce'][0] ?? null);

        $this->assertSame(
            HmacSigner::sign(
                'POST',
                '/api/v1/portal/pedidos',
                (int) $capturado['headers']['X-Timestamp'][0],
                (string) $capturado['headers']['X-Nonce'][0],
                (string) $capturado['body'],
                (string) $config->cliente_secret,
            ),
            $capturado['headers']['X-Signature'][0],
        );

        $payload = json_decode((string) $capturado['body'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($outbox->tracking, $payload['tracking']);
        $this->assertSame('EFECTIVO', $payload['metodo_pago']);
        $this->assertFalse($payload['dte_esperado']);
        $this->assertSame('4.00', $payload['total']);
        $this->assertCount(1, $payload['items']);
        $this->assertSame('Limonada fresca', $payload['items'][0]['nombre']);
        $this->assertSame('4.00', $payload['items'][0]['subtotal']);
    }
}
