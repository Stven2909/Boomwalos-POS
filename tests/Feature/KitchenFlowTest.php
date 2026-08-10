<?php

namespace Tests\Feature;

use App\Enums\EstadoCocina;
use App\Enums\EstadoComercialPedido;
use App\Enums\EstadoMesa;
use App\Enums\MetodoPago;
use App\Enums\TipoImpresora;
use App\Enums\TipoPedido;
use App\Enums\ZonaMesa;
use App\Filament\Pages\Kitchen\KitchenDisplay;
use App\Models\Categoria;
use App\Models\Establecimiento;
use App\Models\EventoAuditoria;
use App\Models\Impresora;
use App\Models\Mesa;
use App\Models\Producto;
use App\Models\SesionCaja;
use App\Models\User;
use App\Services\CobroService;
use App\Services\KitchenService;
use App\Services\PedidoService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class KitchenFlowTest extends TestCase
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

        $this->cashier = User::factory()->create();
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
            'activa' => true,
        ]);

        $category = Categoria::create(['nombre' => 'Bebidas']);
        $this->product = Producto::create([
            'categoria_id' => $category->getKey(),
            'nombre' => 'Limonada de prueba',
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
            'tipo' => TipoImpresora::COMANDA,
            'configuracion' => ['driver' => 'queue'],
        ]);
    }

    public function test_cashier_and_admin_can_open_the_kitchen_display_without_a_cash_session_requirement(): void
    {
        $this->actingAs($this->cashier)
            ->get(KitchenDisplay::getUrl())
            ->assertSuccessful()
            ->assertSee('COCINA · KDS')
            ->assertSee('Para llevar')
            ->assertDontSee('Delivery');

        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        $this->actingAs($admin)
            ->get(KitchenDisplay::getUrl())
            ->assertSuccessful();
    }

    public function test_users_without_operate_kitchen_permission_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(KitchenDisplay::getUrl())
            ->assertForbidden();
    }

    public function test_tanda_transitions_are_ordered_idempotent_and_audited(): void
    {
        $pedido = $this->createOrderWithBatch();
        $batch = $pedido->tandas()->firstOrFail();
        $service = app(KitchenService::class);

        $service->transition($batch, EstadoCocina::EN_PREPARACION, $this->cashier);
        $service->transition($batch, EstadoCocina::EN_PREPARACION, $this->cashier);

        $this->assertSame(EstadoCocina::EN_PREPARACION, $batch->fresh()->estado_cocina);
        $this->assertSame(1, EventoAuditoria::query()
            ->where('entidad_id', $batch->getKey())
            ->where('tipo_evento', 'tanda_iniciada_preparacion')
            ->count());
        $this->assertDatabaseHas('evento_auditorias', [
            'entidad_id' => $batch->getKey(),
            'tipo_evento' => 'tanda_iniciada_preparacion',
        ]);

        $this->expectException(ValidationException::class);
        $service->transition($batch, EstadoCocina::ENTREGADA, $this->cashier);
    }

    public function test_delivered_paid_order_closes_and_releases_the_table(): void
    {
        $pedido = $this->createOrderWithBatch();
        $batch = $pedido->tandas()->firstOrFail();
        $service = app(KitchenService::class);

        $service->transition($batch, EstadoCocina::EN_PREPARACION, $this->cashier);
        $service->transition($batch, EstadoCocina::LISTA, $this->cashier);
        $service->transition($batch, EstadoCocina::ENTREGADA, $this->cashier);

        $this->assertSame(EstadoComercialPedido::ABIERTO, $pedido->fresh()->estado_comercial);

        app(CobroService::class)->charge($pedido, MetodoPago::TARJETA, null, $this->cashier);

        $this->assertDatabaseHas('pedidos', [
            'id' => $pedido->getKey(),
            'estado_comercial' => EstadoComercialPedido::CERRADO->value,
        ]);
        $this->assertDatabaseHas('mesas', [
            'id' => $this->table->getKey(),
            'estado' => EstadoMesa::LIBRE->value,
        ]);
        $this->assertDatabaseHas('evento_auditorias', [
            'entidad_id' => $pedido->getKey(),
            'tipo_evento' => 'pedido_cerrado',
        ]);
    }

    public function test_paid_order_with_another_unresolved_batch_stays_open_until_all_are_delivered(): void
    {
        $pedido = $this->createOrderWithBatch();
        $pedidoService = app(PedidoService::class);
        $secondProduct = Producto::create([
            'categoria_id' => $this->product->categoria_id,
            'nombre' => 'Producto adicional',
            'precio' => 2,
            'disponibilidad' => 'DISPONIBLE',
        ]);

        $firstBatch = $pedido->tandas()->firstOrFail();
        $pedidoService->addProduct($pedido, $secondProduct, $this->cashier);
        $secondBatch = $pedidoService->sendPendingBatch($pedido, $this->cashier);
        $kitchen = app(KitchenService::class);

        foreach ([$firstBatch, $secondBatch] as $batch) {
            $kitchen->transition($batch, EstadoCocina::EN_PREPARACION, $this->cashier);
            $kitchen->transition($batch, EstadoCocina::LISTA, $this->cashier);
        }

        $kitchen->transition($firstBatch, EstadoCocina::ENTREGADA, $this->cashier);
        app(CobroService::class)->charge($pedido, MetodoPago::TARJETA, null, $this->cashier);

        $this->assertSame(EstadoComercialPedido::COBRADO, $pedido->fresh()->estado_comercial);

        $kitchen->transition($secondBatch, EstadoCocina::ENTREGADA, $this->cashier);

        $this->assertSame(EstadoComercialPedido::CERRADO, $pedido->fresh()->estado_comercial);
    }

    private function createOrderWithBatch()
    {
        $pedidoService = app(PedidoService::class);
        $pedido = $pedidoService->startOrder(TipoPedido::MESA, $this->cashier, $this->table->getKey());
        $pedidoService->addProduct($pedido, $this->product, $this->cashier);
        $pedidoService->sendPendingBatch($pedido, $this->cashier);

        return $pedido->fresh(['tandas']);
    }
}
