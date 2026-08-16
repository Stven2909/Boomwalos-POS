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

class CajaReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Establecimiento $establishment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrador');

        $this->establishment = Establecimiento::create(['nombre' => 'Caja Test', 'direccion' => 'Centro']);

        $this->actingAs($this->admin);
    }

    private function createClosedSession(Carbon $apertura, Carbon $cierre): SesionCaja
    {
        $sesion = SesionCaja::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'usuario_apertura_id' => $this->admin->getKey(),
            'usuario_cierre_id' => $this->admin->getKey(),
            'monto_inicial' => 100.00,
            'total_ventas' => 25.00,
            'total_efectivo' => 15.00,
            'total_tarjeta' => 10.00,
            'efectivo_esperado' => 115.00,
            'efectivo_contado' => 114.00,
            'diferencia' => -1.00,
            'fecha_apertura' => $apertura,
            'fecha_cierre' => $cierre,
        ]);

        DB::table('sesion_cajas')->where('id', $sesion->getKey())->update([
            'created_at' => $apertura->toDateTimeString(),
        ]);

        return $sesion->fresh();
    }

    private function createClosedOrder(Carbon $createdAt): Pedido
    {
        $mesa = Mesa::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'numero' => (string) random_int(1, 999),
            'zona' => ZonaMesa::SALON,
            'estado' => EstadoMesa::LIBRE,
            'activa' => true,
        ]);

        $pedido = Pedido::create([
            'numero_seguimiento' => 'CAJA-'.random_int(1000, 9999),
            'tipo_pedido' => TipoPedido::MESA,
            'mesa_id' => $mesa->getKey(),
            'establecimiento_id' => $this->establishment->getKey(),
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

    public function test_sesiones_cerradas_returns_closed_sessions_in_date_range(): void
    {
        $sesion1 = $this->createClosedSession(now()->subDays(3)->startOfDay(), now()->subDays(3)->endOfDay());
        $sesion2 = $this->createClosedSession(now()->subDays(2)->startOfDay(), now()->subDays(2)->endOfDay());

        // Open session — should not appear (no fecha_cierre)
        SesionCaja::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'usuario_apertura_id' => $this->admin->getKey(),
            'monto_inicial' => 50,
            'fecha_apertura' => now(),
        ]);

        $service = app(ReportesService::class);
        $result = $service->sesionesCerradas(
            Carbon::now()->subDays(5)->startOfDay(),
            Carbon::now()->endOfDay(),
        );

        $this->assertCount(2, $result);
        $this->assertEquals($sesion2->getKey(), $result->first()->getKey());
    }

    public function test_sesiones_cerradas_excludes_out_of_range(): void
    {
        $this->createClosedSession(now()->subDays(10)->startOfDay(), now()->subDays(10)->endOfDay());

        $service = app(ReportesService::class);
        $result = $service->sesionesCerradas(
            Carbon::now()->subDays(3)->startOfDay(),
            Carbon::now()->endOfDay(),
        );

        $this->assertCount(0, $result);
    }

    public function test_sesiones_cerradas_filters_by_establecimiento(): void
    {
        $otherEstablishment = Establecimiento::create(['nombre' => 'Otra Sucursal', 'direccion' => 'Sur']);

        $this->createClosedSession(now()->subDays(1)->startOfDay(), now()->subDays(1)->endOfDay());

        SesionCaja::create([
            'establecimiento_id' => $otherEstablishment->getKey(),
            'usuario_apertura_id' => $this->admin->getKey(),
            'usuario_cierre_id' => $this->admin->getKey(),
            'monto_inicial' => 50,
            'fecha_apertura' => now()->subDays(1),
            'fecha_cierre' => now()->subDays(1),
        ]);

        $service = app(ReportesService::class);
        $result = $service->sesionesCerradas(
            Carbon::now()->subDays(3)->startOfDay(),
            Carbon::now()->endOfDay(),
            $this->establishment->getKey(),
        );

        $this->assertCount(1, $result);
        $this->assertEquals($this->establishment->getKey(), $result->first()->establecimiento_id);
    }

    public function test_sesion_detalle_pagos_returns_payments_for_session(): void
    {
        $sesion = $this->createClosedSession(now()->subDays(1)->startOfDay(), now()->subDays(1)->endOfDay());

        $pedido = $this->createClosedOrder(now()->subDays(1));
        DetallePedido::create([
            'pedido_id' => $pedido->getKey(),
            'tanda_id' => null,
            'estado_linea' => EstadoLineaPedido::ACTIVA,
            'producto_id' => Producto::create([
                'categoria_id' => Categoria::create(['nombre' => 'Cat'])->getKey(),
                'nombre' => 'Test',
                'precio' => 10,
                'disponibilidad' => DisponibilidadProducto::DISPONIBLE,
            ])->getKey(),
            'cantidad' => 1,
            'precio_unitario' => 10,
        ]);

        Pago::create([
            'pedido_id' => $pedido->getKey(),
            'sesion_caja_id' => $sesion->getKey(),
            'metodo_pago' => MetodoPago::EFECTIVO,
            'monto_recibido' => 15.00,
            'cambio_devuelto' => 5.00,
        ]);

        $service = app(ReportesService::class);
        $pagos = $service->sesionDetallePagos($sesion->getKey());

        $this->assertCount(1, $pagos);
        $this->assertEquals($pedido->getKey(), $pagos->first()->pedido_id);
        $this->assertEquals(MetodoPago::EFECTIVO, $pagos->first()->metodo_pago);
        $this->assertEquals(15.00, $pagos->first()->monto_recibido);
        $this->assertEquals(5.00, $pagos->first()->cambio_devuelto);
    }
}
