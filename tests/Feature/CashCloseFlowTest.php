<?php

namespace Tests\Feature;

use App\Enums\EstadoMesa;
use App\Enums\MetodoPago;
use App\Enums\TipoPedido;
use App\Enums\ZonaMesa;
use App\Filament\Pages\Cash\CloseSession;
use App\Filament\Pages\Cash\OpenSession;
use App\Models\Categoria;
use App\Models\Establecimiento;
use App\Models\EventoAuditoria;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\SesionCaja;
use App\Models\User;
use App\Services\CierreCajaService;
use App\Services\CobroService;
use App\Services\PedidoService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CashCloseFlowTest extends TestCase
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
    }

    private function openSession(float|string $montoInicial = '50.00'): SesionCaja
    {
        return SesionCaja::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'usuario_apertura_id' => $this->cashier->getKey(),
            'monto_inicial' => $montoInicial,
            'fecha_apertura' => now(),
        ]);
    }

    private function cobrar(Pedido $pedido, MetodoPago $metodo, ?string $montoRecibido): void
    {
        app(CobroService::class)->charge($pedido, $metodo, $montoRecibido, $this->cashier);
    }

    public function test_expected_cash_sums_initial_cash_and_net_cash_payments(): void
    {
        $sesion = $this->openSession('100.00');

        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::MESA, $this->cashier, $this->table->getKey());
        $service->addProduct($pedido, $this->product, $this->cashier);
        $service->sendPendingBatch($pedido, $this->cashier);

        $this->cobrar($pedido, MetodoPago::EFECTIVO, '10.00');

        $secondTable = Mesa::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'numero' => '9',
            'zona' => ZonaMesa::SALON,
            'estado' => EstadoMesa::LIBRE,
        ]);
        $pedido2 = $service->startOrder(TipoPedido::MESA, $this->cashier, $secondTable->getKey());
        $service->addProduct($pedido2, $this->product, $this->cashier);
        $service->sendPendingBatch($pedido2, $this->cashier);
        $this->cobrar($pedido2, MetodoPago::EFECTIVO, '6.00');

        $thirdTable = Mesa::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'numero' => '10',
            'zona' => ZonaMesa::SALON,
            'estado' => EstadoMesa::LIBRE,
        ]);
        $pedido3 = $service->startOrder(TipoPedido::MESA, $this->cashier, $thirdTable->getKey());
        $service->addProduct($pedido3, $this->product, $this->cashier);
        $service->sendPendingBatch($pedido3, $this->cashier);
        $this->cobrar($pedido3, MetodoPago::TARJETA, null);

        $esperado = app(CierreCajaService::class)->calcularEsperado($sesion);

        // 100 inicial + (10 - 6 cambio) + (6 - 2 cambio) = 108. Tarjeta excluida.
        $this->assertSame('108.00', $esperado);
        $this->assertDatabaseCount('pagos', 3);
    }

    public function test_close_session_persists_expected_counted_and_difference(): void
    {
        $sesion = $this->openSession('100.00');

        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::MESA, $this->cashier, $this->table->getKey());
        $service->addProduct($pedido, $this->product, $this->cashier);
        $service->sendPendingBatch($pedido, $this->cashier);
        $this->cobrar($pedido, MetodoPago::EFECTIVO, '10.00');

        $cerrada = app(CierreCajaService::class)->cerrar($sesion, '104.00', $this->cashier);

        $this->assertEquals('100.00', $cerrada->monto_inicial);
        $this->assertEquals('104.00', $cerrada->efectivo_esperado);
        $this->assertEquals('104.00', $cerrada->efectivo_contado);
        $this->assertEquals('0.00', $cerrada->diferencia);
        $this->assertSame($this->cashier->getKey(), $cerrada->usuario_cierre_id);
        $this->assertNotNull($cerrada->fecha_cierre);
    }

    public function test_close_session_records_negative_difference_with_bcmath_precision(): void
    {
        $sesion = $this->openSession('100.00');

        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::MESA, $this->cashier, $this->table->getKey());
        $service->addProduct($pedido, $this->product, $this->cashier);
        $service->sendPendingBatch($pedido, $this->cashier);
        $this->cobrar($pedido, MetodoPago::EFECTIVO, '10.00');

        $cerrada = app(CierreCajaService::class)->cerrar($sesion, '103.50', $this->cashier);

        $this->assertEquals('104.00', $cerrada->efectivo_esperado);
        $this->assertEquals('103.50', $cerrada->efectivo_contado);
        $this->assertEquals('-0.50', $cerrada->diferencia);
    }

    public function test_second_close_is_rejected_and_keeps_first_closure(): void
    {
        $sesion = $this->openSession('100.00');
        app(CierreCajaService::class)->cerrar($sesion, '100.00', $this->cashier);

        try {
            app(CierreCajaService::class)->cerrar($sesion, '200.00', $this->cashier);
            $this->fail('El segundo cierre debía ser rechazado.');
        } catch (ValidationException) {
            // La sesión debe conservar los datos del primer cierre.
        }

        $this->assertSame(1, SesionCaja::query()->count());
        $this->assertSame('100.00', $sesion->fresh()->efectivo_contado);
    }

    public function test_close_generates_caja_cerrada_audit_event(): void
    {
        $sesion = $this->openSession('100.00');

        app(CierreCajaService::class)->cerrar($sesion, '100.00', $this->cashier);

        $this->assertDatabaseHas('evento_auditorias', [
            'entidad_tipo' => SesionCaja::class,
            'entidad_id' => $sesion->getKey(),
            'usuario_id' => $this->cashier->getKey(),
            'tipo_evento' => 'caja_cerrada',
        ]);
    }

    public function test_close_with_pending_open_orders_records_them_in_audit_payload(): void
    {
        $sesion = $this->openSession('100.00');

        $service = app(PedidoService::class);
        $pedidoAbierto = $service->startOrder(TipoPedido::MESA, $this->cashier, $this->table->getKey());
        $service->addProduct($pedidoAbierto, $this->product, $this->cashier);

        $cerrada = app(CierreCajaService::class)->cerrar($sesion, '100.00', $this->cashier);

        $evento = EventoAuditoria::query()
            ->where('tipo_evento', 'caja_cerrada')
            ->where('entidad_id', $cerrada->getKey())
            ->firstOrFail();

        $this->assertSame([[
            'id' => $pedidoAbierto->getKey(),
            'numero_seguimiento' => $pedidoAbierto->numero_seguimiento,
        ]], $evento->payload['pedidos_abiertos']);
        $this->assertSame('100.00', $evento->payload['efectivo_esperado']);
        $this->assertSame('100.00', $evento->payload['efectivo_contado']);
        $this->assertSame('0.00', $evento->payload['diferencia']);
    }

    public function test_open_session_generates_caja_abierta_audit_event(): void
    {
        Livewire::actingAs($this->cashier)
            ->test(OpenSession::class)
            ->set('montoInicial', '50.00')
            ->call('openSession')
            ->assertHasNoErrors();

        $sesion = SesionCaja::firstOrFail();

        $this->assertDatabaseHas('evento_auditorias', [
            'entidad_tipo' => SesionCaja::class,
            'entidad_id' => $sesion->getKey(),
            'usuario_id' => $this->cashier->getKey(),
            'tipo_evento' => 'caja_abierta',
        ]);
    }

    public function test_close_session_page_requires_cerrar_caja_permission(): void
    {
        $guest = User::factory()->create();

        $this->actingAs($guest)
            ->get(CloseSession::getUrl())
            ->assertForbidden();
    }

    public function test_close_session_page_redirects_without_an_active_session(): void
    {
        $this->actingAs($this->cashier)
            ->get(CloseSession::getUrl())
            ->assertRedirect();
    }

    public function test_close_session_page_shows_expected_cash_and_live_difference(): void
    {
        $sesion = $this->openSession('100.00');

        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::MESA, $this->cashier, $this->table->getKey());
        $service->addProduct($pedido, $this->product, $this->cashier);
        $service->sendPendingBatch($pedido, $this->cashier);
        $this->cobrar($pedido, MetodoPago::EFECTIVO, '10.00');

        $component = Livewire::actingAs($this->cashier)
            ->test(CloseSession::class)
            ->assertSee('$104.00');

        $component
            ->set('efectivoContado', '105.00')
            ->assertSet('diferencia', '1.00');

        $component
            ->set('efectivoContado', '103.50')
            ->assertSet('diferencia', '-0.50');

        $component
            ->call('closeSession')
            ->assertHasNoErrors();

        $this->assertSame('103.50', $sesion->fresh()->efectivo_contado);
        $this->assertSame('-0.50', $sesion->fresh()->diferencia);
        $this->assertNotNull($sesion->fresh()->fecha_cierre);
    }
}
