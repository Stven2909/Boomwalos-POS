<?php

namespace Tests\Feature\Informes;

use App\Enums\DisponibilidadProducto;
use App\Enums\EstadoComercialPedido;
use App\Enums\EstadoLineaPedido;
use App\Enums\EstadoMesa;
use App\Enums\MetodoPago;
use App\Enums\OrigenPedido;
use App\Enums\TipoPedido;
use App\Enums\ZonaMesa;
use App\Models\Categoria;
use App\Models\DetallePedido;
use App\Models\Establecimiento;
use App\Models\Mesa;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\SesionCaja;
use App\Models\User;
use App\Services\ReportesService;
use Carbon\Carbon;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VentasReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Establecimiento $sucursalA;

    private Establecimiento $sucursalB;

    private Producto $hamburguesa;

    private Producto $papas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrador');

        $this->sucursalA = Establecimiento::create(['nombre' => 'Sucursal A', 'direccion' => 'Centro']);
        $this->sucursalB = Establecimiento::create(['nombre' => 'Sucursal B', 'direccion' => 'Norte']);

        $cat = Categoria::create(['nombre' => 'Comida']);
        $this->hamburguesa = Producto::create([
            'categoria_id' => $cat->getKey(),
            'nombre' => 'Hamburguesa',
            'precio' => 8.50,
            'disponibilidad' => DisponibilidadProducto::DISPONIBLE,
        ]);
        $this->papas = Producto::create([
            'categoria_id' => $cat->getKey(),
            'nombre' => 'Papas Fritas',
            'precio' => 3.00,
            'disponibilidad' => DisponibilidadProducto::DISPONIBLE,
        ]);

        $this->actingAs($this->admin);
    }

    private function createClosedOrder(Establecimiento $est, float $createdDaysAgo): Pedido
    {
        $mesa = Mesa::create([
            'establecimiento_id' => $est->getKey(),
            'numero' => (string) random_int(1, 999),
            'zona' => ZonaMesa::SALON,
            'estado' => EstadoMesa::LIBRE,
            'activa' => true,
        ]);

        $createdAt = Carbon::now()->subDays($createdDaysAgo);

        $pedido = Pedido::create([
            'numero_seguimiento' => 'VNT-'.random_int(1000, 9999),
            'tipo_pedido' => TipoPedido::MESA,
            'mesa_id' => $mesa->getKey(),
            'establecimiento_id' => $est->getKey(),
            'usuario_id' => $this->admin->getKey(),
            'origen_pedido' => OrigenPedido::CAJA,
            'codigo_corto' => random_int(1, 999),
            'fecha_codigo' => $createdAt->toDateString(),
            'estado_comercial' => EstadoComercialPedido::COBRADO,
        ]);

        DB::table('pedidos')->where('id', $pedido->getKey())->update([
            'created_at' => $createdAt->toDateTimeString(),
            'updated_at' => $createdAt->toDateTimeString(),
        ]);

        return $pedido->fresh();
    }

    private function addDetalle(Pedido $pedido, Producto $producto, int $cantidad, SesionCaja $sesion, MetodoPago $metodo): void
    {
        DetallePedido::create([
            'pedido_id' => $pedido->getKey(),
            'tanda_id' => null,
            'estado_linea' => EstadoLineaPedido::ACTIVA,
            'producto_id' => $producto->getKey(),
            'cantidad' => $cantidad,
            'precio_unitario' => $producto->precio,
        ]);

        Pago::create([
            'pedido_id' => $pedido->getKey(),
            'sesion_caja_id' => $sesion->getKey(),
            'metodo_pago' => $metodo,
            'monto_recibido' => $producto->precio * $cantidad,
            'cambio_devuelto' => 0,
        ]);
    }

    public function test_ventas_resumen_returns_correct_totals(): void
    {
        $sesion = SesionCaja::create([
            'establecimiento_id' => $this->sucursalA->getKey(),
            'usuario_apertura_id' => $this->admin->getKey(),
            'monto_inicial' => 50,
            'fecha_apertura' => now()->subDays(3),
        ]);

        $pedido = $this->createClosedOrder($this->sucursalA, 2);
        $this->addDetalle($pedido, $this->hamburguesa, 2, $sesion, MetodoPago::EFECTIVO);

        $service = app(ReportesService::class);
        $resumen = $service->ventasResumen(
            Carbon::now()->subDays(3)->startOfDay(),
            Carbon::now()->endOfDay(),
        );

        $this->assertEquals(17.00, $resumen['total_ventas']);
        $this->assertEquals(1, $resumen['cantidad_pedidos']);
        $this->assertEquals(17.00, $resumen['ticket_promedio']);
    }

    public function test_ventas_resumen_excludes_orders_outside_date_range(): void
    {
        $sesion = SesionCaja::create([
            'establecimiento_id' => $this->sucursalA->getKey(),
            'usuario_apertura_id' => $this->admin->getKey(),
            'monto_inicial' => 50,
            'fecha_apertura' => now()->subDays(10),
        ]);

        // 10 days ago — outside range
        $pedido = $this->createClosedOrder($this->sucursalA, 10);
        $this->addDetalle($pedido, $this->hamburguesa, 1, $sesion, MetodoPago::EFECTIVO);

        $service = app(ReportesService::class);
        $resumen = $service->ventasResumen(
            Carbon::now()->subDays(3)->startOfDay(),
            Carbon::now()->endOfDay(),
        );

        $this->assertEquals(0, $resumen['total_ventas']);
        $this->assertEquals(0, $resumen['cantidad_pedidos']);
    }

    public function test_ventas_resumen_filters_by_establecimiento(): void
    {
        $sesionA = SesionCaja::create([
            'establecimiento_id' => $this->sucursalA->getKey(),
            'usuario_apertura_id' => $this->admin->getKey(),
            'monto_inicial' => 50,
            'fecha_apertura' => now()->subDays(2),
        ]);
        $sesionB = SesionCaja::create([
            'establecimiento_id' => $this->sucursalB->getKey(),
            'usuario_apertura_id' => $this->admin->getKey(),
            'monto_inicial' => 50,
            'fecha_apertura' => now()->subDays(2),
        ]);

        $pedidoA = $this->createClosedOrder($this->sucursalA, 1);
        $this->addDetalle($pedidoA, $this->hamburguesa, 1, $sesionA, MetodoPago::EFECTIVO);

        $pedidoB = $this->createClosedOrder($this->sucursalB, 1);
        $this->addDetalle($pedidoB, $this->papas, 3, $sesionB, MetodoPago::EFECTIVO);

        $service = app(ReportesService::class);
        $resumen = $service->ventasResumen(
            Carbon::now()->subDays(3)->startOfDay(),
            Carbon::now()->endOfDay(),
            $this->sucursalA->getKey(),
        );

        $this->assertEquals(8.50, $resumen['total_ventas']);
        $this->assertEquals(1, $resumen['cantidad_pedidos']);
    }

    public function test_ventas_por_metodo_pago_groups_correctly(): void
    {
        $sesion = SesionCaja::create([
            'establecimiento_id' => $this->sucursalA->getKey(),
            'usuario_apertura_id' => $this->admin->getKey(),
            'monto_inicial' => 50,
            'fecha_apertura' => now()->subDays(2),
        ]);

        $pedido1 = $this->createClosedOrder($this->sucursalA, 1);
        $this->addDetalle($pedido1, $this->hamburguesa, 1, $sesion, MetodoPago::EFECTIVO);

        $pedido2 = $this->createClosedOrder($this->sucursalA, 1);
        $this->addDetalle($pedido2, $this->papas, 1, $sesion, MetodoPago::TARJETA);

        $service = app(ReportesService::class);
        $metodos = $service->ventasPorMetodoPago(
            Carbon::now()->subDays(3)->startOfDay(),
            Carbon::now()->endOfDay(),
        );

        $this->assertCount(2, $metodos);

        $efectivo = $metodos->firstWhere('metodo_pago', MetodoPago::EFECTIVO->value);
        $this->assertNotNull($efectivo);
        $this->assertEquals(8.50, $efectivo['total']);

        $tarjeta = $metodos->firstWhere('metodo_pago', MetodoPago::TARJETA->value);
        $this->assertNotNull($tarjeta);
        $this->assertEquals(3.00, $tarjeta['total']);
    }

    public function test_ventas_por_metodo_pago_percentages_sum_to_100(): void
    {
        $sesion = SesionCaja::create([
            'establecimiento_id' => $this->sucursalA->getKey(),
            'usuario_apertura_id' => $this->admin->getKey(),
            'monto_inicial' => 50,
            'fecha_apertura' => now()->subDays(2),
        ]);

        $pedido1 = $this->createClosedOrder($this->sucursalA, 1);
        $this->addDetalle($pedido1, $this->hamburguesa, 1, $sesion, MetodoPago::EFECTIVO);

        $pedido2 = $this->createClosedOrder($this->sucursalA, 1);
        $this->addDetalle($pedido2, $this->papas, 2, $sesion, MetodoPago::TARJETA);

        $service = app(ReportesService::class);
        $metodos = $service->ventasPorMetodoPago(
            Carbon::now()->subDays(3)->startOfDay(),
            Carbon::now()->endOfDay(),
        );

        $totalPorcentaje = $metodos->sum('porcentaje');
        $this->assertEqualsWithDelta(100.0, $totalPorcentaje, 0.1);
    }

    public function test_top_productos_returns_sorted_by_total(): void
    {
        $sesion = SesionCaja::create([
            'establecimiento_id' => $this->sucursalA->getKey(),
            'usuario_apertura_id' => $this->admin->getKey(),
            'monto_inicial' => 50,
            'fecha_apertura' => now()->subDays(2),
        ]);

        $pedido = $this->createClosedOrder($this->sucursalA, 1);
        DetallePedido::create([
            'pedido_id' => $pedido->getKey(),
            'tanda_id' => null,
            'estado_linea' => EstadoLineaPedido::ACTIVA,
            'producto_id' => $this->hamburguesa->getKey(),
            'cantidad' => 3,
            'precio_unitario' => $this->hamburguesa->precio,
        ]);
        DetallePedido::create([
            'pedido_id' => $pedido->getKey(),
            'tanda_id' => null,
            'estado_linea' => EstadoLineaPedido::ACTIVA,
            'producto_id' => $this->papas->getKey(),
            'cantidad' => 1,
            'precio_unitario' => $this->papas->precio,
        ]);

        $service = app(ReportesService::class);
        $top = $service->topProductos(
            Carbon::now()->subDays(3)->startOfDay(),
            Carbon::now()->endOfDay(),
        );

        $this->assertCount(2, $top);
        $this->assertEquals('Hamburguesa', $top->first()['nombre']);
        $this->assertEquals(3, $top->first()['cantidad_vendida']);
        $this->assertFalse((bool) $top->first()['es_combo']);
    }

    public function test_ventas_por_sucursal_groups_by_establishment(): void
    {
        $sesionA = SesionCaja::create([
            'establecimiento_id' => $this->sucursalA->getKey(),
            'usuario_apertura_id' => $this->admin->getKey(),
            'monto_inicial' => 50,
            'fecha_apertura' => now()->subDays(2),
        ]);
        $sesionB = SesionCaja::create([
            'establecimiento_id' => $this->sucursalB->getKey(),
            'usuario_apertura_id' => $this->admin->getKey(),
            'monto_inicial' => 50,
            'fecha_apertura' => now()->subDays(2),
        ]);

        $pedidoA = $this->createClosedOrder($this->sucursalA, 1);
        $this->addDetalle($pedidoA, $this->hamburguesa, 1, $sesionA, MetodoPago::EFECTIVO);

        $pedidoB = $this->createClosedOrder($this->sucursalB, 1);
        $this->addDetalle($pedidoB, $this->hamburguesa, 1, $sesionB, MetodoPago::EFECTIVO);

        $service = app(ReportesService::class);
        $ventas = $service->ventasPorSucursal(
            Carbon::now()->subDays(3)->startOfDay(),
            Carbon::now()->endOfDay(),
        );

        $this->assertCount(2, $ventas);
        $this->assertEquals(8.50, $ventas->firstWhere('establecimiento_id', $this->sucursalA->getKey())['total']);
        $this->assertEquals(8.50, $ventas->firstWhere('establecimiento_id', $this->sucursalB->getKey())['total']);
    }
}
