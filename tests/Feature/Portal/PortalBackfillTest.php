<?php

namespace Tests\Feature\Portal;

use App\Enums\MetodoPago;
use App\Enums\TipoPedido;
use App\Models\Categoria;
use App\Models\ConfiguracionFiscal;
use App\Models\Establecimiento;
use App\Models\Producto;
use App\Models\SesionCaja;
use App\Models\User;
use App\Models\VentaFiscalPos;
use App\Services\CobroService;
use App\Services\PedidoService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PortalBackfillTest extends TestCase
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
            'usuario' => '61',
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

        // La sonda de conectividad falla rápido; Http::fake intercepta el envío.
        config(['fiscal.url' => 'http://127.0.0.1:1']);
        config(['portal.corte' => null]);

        // El snapshot del cobro se encola; aquí probamos el envío directo del backfill.
        Queue::fake();

        $this->fiscalConfig();
    }

    private function fiscalConfig(): ConfiguracionFiscal
    {
        return ConfiguracionFiscal::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'razon_social' => 'Pupusería Demo',
            'codigo_establecimiento' => 'M001',
            'codigo_punto_venta' => 'P001',
            'fiscal_habilitada' => true,
            'cliente_key' => 'est-test',
            'cliente_secret' => 'secreto-backfill',
            'intentos_maximos' => 3,
        ]);
    }

    private function order(MetodoPago $metodo, Carbon $fecha): array
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier);
        $service->addProduct($pedido, $this->product, $this->cashier);

        $pago = app(CobroService::class)->charge(
            $pedido,
            $metodo,
            $metodo === MetodoPago::EFECTIVO ? '10.00' : null,
            $this->cashier,
            $metodo === MetodoPago::TARJETA ? ['aprobada' => true, 'referencia' => 'TEST-REF'] : null,
        );

        $pedido->forceFill(['created_at' => $fecha, 'updated_at' => $fecha])->save();
        $pago->forceFill(['created_at' => $fecha, 'updated_at' => $fecha])->save();

        return [$pedido->fresh(), $pago->fresh()];
    }

    private function runBackfill(array $options = []): void
    {
        $this->artisan('portal:backfill', $options)->assertSuccessful();
    }

    private function body(Request $request): array
    {
        return json_decode($request->body(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_efectivo_envia_snapshot_sin_vinculo(): void
    {
        Http::fake(['*' => Http::response(['recibido' => true], 200)]);

        [$pedido] = $this->order(MetodoPago::EFECTIVO, now());

        $this->runBackfill([
            '--desde' => now()->toDateString(),
            '--hasta' => now()->toDateString(),
        ]);

        Http::assertSent(function (Request $request) use ($pedido): bool {
            $body = $this->body($request);

            return $request->url() === 'http://127.0.0.1:1/api/v1/portal/pedidos'
                && $body['tracking'] === $pedido->numero_seguimiento
                && $body['metodo_pago'] === 'EFECTIVO'
                && $body['dte_esperado'] === false
                && ! array_key_exists('vinculo', $body);
        });
    }

    public function test_tarjeta_envia_vinculo_con_identificadores_exactos(): void
    {
        Http::fake(['*' => Http::response(['recibido' => true], 200)]);

        [$pedido] = $this->order(MetodoPago::TARJETA, now());

        $venta = VentaFiscalPos::query()->where('pedido_id', $pedido->getKey())->firstOrFail();
        $venta->update([
            'codigo_generacion' => '11111111-2222-3333-4444-555555555555',
            'numero_control' => 'DTE-01-M001P001-000000000000001',
            'sello_recepcion' => 'SELLO-BACKFILL-1',
        ]);

        $this->runBackfill([
            '--desde' => now()->toDateString(),
            '--hasta' => now()->toDateString(),
        ]);

        Http::assertSent(function (Request $request) use ($pedido): bool {
            $body = $this->body($request);

            return $body['tracking'] === $pedido->numero_seguimiento
                && $body['dte_esperado'] === true
                && ($body['vinculo']['codigo_generacion'] ?? null) === '11111111-2222-3333-4444-555555555555'
                && ($body['vinculo']['numero_control'] ?? null) === 'DTE-01-M001P001-000000000000001'
                && ($body['vinculo']['sello_recepcion'] ?? null) === 'SELLO-BACKFILL-1';
        });
    }

    public function test_omite_pedidos_fuera_de_la_ventana(): void
    {
        Http::fake();

        $this->order(MetodoPago::EFECTIVO, now()->subDays(10));

        $this->runBackfill([
            '--desde' => now()->subDay()->toDateString(),
            '--hasta' => now()->toDateString(),
        ]);

        Http::assertNothingSent();
    }

    public function test_no_cruza_al_mes_anterior(): void
    {
        Http::fake();

        $this->order(MetodoPago::EFECTIVO, now()->subMonthNoOverflow()->startOfMonth()->addDays(2));

        $this->runBackfill([
            '--hasta' => now()->toDateString(),
        ]);

        Http::assertNothingSent();
    }

    public function test_respeta_portal_corte(): void
    {
        Http::fake();
        config(['portal.corte' => now()->toDateString()]);

        $this->order(MetodoPago::EFECTIVO, now()->subDay());

        $this->runBackfill([
            '--hasta' => now()->toDateString(),
        ]);

        Http::assertNothingSent();
    }

    public function test_409_deja_el_pedido_en_revision_y_continua(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'DTE_NO_ENCONTRADO'], 409),
        ]);

        [$pedido] = $this->order(MetodoPago::TARJETA, now());
        $venta = VentaFiscalPos::query()->where('pedido_id', $pedido->getKey())->firstOrFail();
        $venta->update(['codigo_generacion' => 'UUID-NO-ENCONTRADO']);

        $this->artisan('portal:backfill', [
            '--desde' => now()->toDateString(),
            '--hasta' => now()->toDateString(),
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('DTE_NO_ENCONTRADO');
    }

    public function test_dry_run_no_envia(): void
    {
        Http::fake();

        $this->order(MetodoPago::EFECTIVO, now());

        $this->artisan('portal:backfill', [
            '--desde' => now()->toDateString(),
            '--hasta' => now()->toDateString(),
            '--dry-run' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('dry-run');

        Http::assertNothingSent();
    }

    public function test_es_reejecutable_sin_duplicar_el_tracking(): void
    {
        Http::fake(['*' => Http::response(['recibido' => true], 200)]);

        [$pedido] = $this->order(MetodoPago::EFECTIVO, now());

        $this->runBackfill([
            '--desde' => now()->toDateString(),
            '--hasta' => now()->toDateString(),
        ]);
        $this->runBackfill([
            '--desde' => now()->toDateString(),
            '--hasta' => now()->toDateString(),
        ]);

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => $this->body($request)['tracking'] === $pedido->numero_seguimiento);
    }

    public function test_sin_configuracion_hmac_omite_sin_enviar(): void
    {
        Http::fake();
        ConfiguracionFiscal::query()->delete();

        $this->order(MetodoPago::EFECTIVO, now());

        $this->artisan('portal:backfill', [
            '--desde' => now()->toDateString(),
            '--hasta' => now()->toDateString(),
        ])->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_rechaza_fecha_invalida(): void
    {
        $this->artisan('portal:backfill', ['--desde' => '2026-13-01'])
            ->assertFailed();
    }

    public function test_limite_acota_los_pedidos_procesados(): void
    {
        Http::fake(['*' => Http::response(['recibido' => true], 200)]);

        $this->order(MetodoPago::EFECTIVO, now());
        $this->order(MetodoPago::EFECTIVO, now());

        $this->runBackfill([
            '--desde' => now()->toDateString(),
            '--hasta' => now()->toDateString(),
            '--limite' => 1,
        ]);

        Http::assertSentCount(1);
    }
}
