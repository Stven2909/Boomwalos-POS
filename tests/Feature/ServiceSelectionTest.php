<?php

namespace Tests\Feature;

use App\Enums\MetodoPago;
use App\Enums\TipoPedido;
use App\Filament\Pages\Pos\ServiceSelection;
use App\Models\Categoria;
use App\Models\Establecimiento;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\SesionCaja;
use App\Models\User;
use App\Services\CobroService;
use App\Services\PedidoService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceSelectionTest extends TestCase
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
            'usuario' => '42',
            'password' => '1234',
        ]);
        $this->cashier->assignRole('cajero');

        $this->establishment = Establecimiento::create([
            'nombre' => 'Los Boomwalos',
            'direccion' => 'Dirección de prueba',
        ]);

        $category = Categoria::create(['nombre' => 'Bebidas']);
        $this->product = Producto::create([
            'categoria_id' => $category->getKey(),
            'nombre' => 'Jugo natural',
            'precio' => 4,
            'disponibilidad' => 'DISPONIBLE',
        ]);
    }

    private function openSession(?\DateTimeInterface $fechaApertura = null): SesionCaja
    {
        return SesionCaja::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'usuario_apertura_id' => $this->cashier->getKey(),
            'monto_inicial' => 0,
            'fecha_apertura' => $fechaApertura ?? now(),
        ]);
    }

    private function newChargedOrder(MetodoPago $metodo, ?string $montoRecibido): Pedido
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier);
        $service->addProduct($pedido, $this->product, $this->cashier);

        $tarjeta = $metodo === MetodoPago::TARJETA ? ['aprobada' => true, 'referencia' => 'TEST-REF'] : null;
        app(CobroService::class)->chargeAndSend($pedido, $metodo, $montoRecibido, $this->cashier, $tarjeta);

        return $pedido;
    }

    public function test_service_selection_shows_zero_sales_with_open_session(): void
    {
        $this->openSession();

        $page = app(ServiceSelection::class);

        $this->assertSame('0.00', $page->turnoSales);
        $this->assertSame('0.00', $page->daySales);
        $this->assertSame([], $page->cashAlerts);
    }

    public function test_service_selection_sums_turn_sales_net_of_change(): void
    {
        $this->openSession();

        $this->newChargedOrder(MetodoPago::EFECTIVO, '10.00');
        $this->newChargedOrder(MetodoPago::TARJETA, null);

        $page = app(ServiceSelection::class);

        $this->assertSame('8.00', $page->turnoSales);
        $this->assertSame('8.00', $page->daySales);
    }

    public function test_service_selection_day_sales_exclude_yesterday_pagos(): void
    {
        $this->openSession();

        $cashOrder = $this->newChargedOrder(MetodoPago::EFECTIVO, '10.00');
        Pago::query()->where('pedido_id', $cashOrder->getKey())->update(['created_at' => now()->subDay()->startOfDay()]);
        $this->newChargedOrder(MetodoPago::TARJETA, null);

        $page = app(ServiceSelection::class);

        $this->assertSame('8.00', $page->turnoSales);
        $this->assertSame('4.00', $page->daySales);
    }

    public function test_service_selection_alerts_when_no_open_session(): void
    {
        $page = app(ServiceSelection::class);

        $this->assertNull($page->turnoSales);
        $this->assertSame('0.00', $page->daySales);
        $this->assertSame('No hay turno abierto', $page->cashAlerts[0]['titulo']);
        $this->assertSame('error', $page->cashAlerts[0]['tipo']);
    }

    public function test_service_selection_alerts_when_turn_left_open_previous_day(): void
    {
        $this->openSession(now()->subDay()->startOfDay());

        $page = app(ServiceSelection::class);

        $this->assertSame('Turno sin cerrar', $page->cashAlerts[0]['titulo']);
        $this->assertSame('warning', $page->cashAlerts[0]['tipo']);
    }

    public function test_service_selection_page_renders_sales_summary_and_alerts(): void
    {
        $this->openSession();
        $this->newChargedOrder(MetodoPago::EFECTIVO, '10.00');

        $this->actingAs($this->cashier)
            ->get(ServiceSelection::getUrl())
            ->assertSuccessful()
            ->assertSee('Ventas del turno')
            ->assertSee('Ventas del día');
    }
}
