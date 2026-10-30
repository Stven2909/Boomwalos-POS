<?php

namespace Tests\Feature;

use App\Contracts\EstablishmentContextInterface;
use App\Enums\EstadoComercialPedido;
use App\Enums\EstadoMesa;
use App\Enums\TipoPedido;
use App\Filament\Pages\Pos\OrderEntry;
use App\Models\Categoria;
use App\Models\Establecimiento;
use App\Models\EventoAuditoria;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\SesionCaja;
use App\Models\User;
use App\Services\PedidoService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerNameTest extends TestCase
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
            'usuario' => '77',
            'password' => '1234',
        ]);
        $this->cashier->assignRole('cajero');

        $this->establishment = Establecimiento::create([
            'nombre' => 'Pupusería Cliente',
            'direccion' => 'Dirección de prueba',
        ]);

        $this->cashier->establecimientos()->attach($this->establishment->getKey());
        app(EstablishmentContextInterface::class)->set($this->establishment->getKey());
        $this->actingAs($this->cashier);

        SesionCaja::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'usuario_apertura_id' => $this->cashier->getKey(),
            'monto_inicial' => '50.00',
            'fecha_apertura' => now(),
        ]);

        $this->table = Mesa::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'numero' => '4',
            'zona' => 'SALON',
            'estado' => EstadoMesa::LIBRE,
        ]);

        $category = Categoria::create(['nombre' => 'Bebidas']);
        $this->product = Producto::create([
            'categoria_id' => $category->getKey(),
            'nombre' => 'Café',
            'precio' => 2,
            'disponibilidad' => 'DISPONIBLE',
        ]);
    }

    public function test_order_can_start_without_customer_name(): void
    {
        $pedido = app(PedidoService::class)->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier);

        $this->assertNull($pedido->nombre_cliente);
        $this->assertSame('Sin nombre', $pedido->nombreClienteLabel());
    }

    public function test_order_can_start_with_customer_name(): void
    {
        $pedido = app(PedidoService::class)->startOrder(
            TipoPedido::PARA_LLEVAR,
            $this->cashier,
            null,
            \App\Enums\OrigenPedido::CAJA,
            '  María   López  ',
        );

        $this->assertSame('María López', $pedido->nombre_cliente);
    }

    public function test_customer_name_can_be_added_and_cleared_while_order_is_open(): void
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier);

        $pedido = $service->setCustomerName($pedido, 'Carlos Pérez', $this->cashier);
        $this->assertSame('Carlos Pérez', $pedido->nombre_cliente);

        $pedido = $service->setCustomerName($pedido, '   ', $this->cashier);
        $this->assertNull($pedido->nombre_cliente);

        $this->assertDatabaseHas('pedidos', [
            'id' => $pedido->getKey(),
            'nombre_cliente' => null,
        ]);
    }

    public function test_customer_name_strips_control_characters_and_collapses_spaces(): void
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier);

        $pedido = $service->setCustomerName($pedido, "  Ana \t\n  Gómez  ", $this->cashier);

        $this->assertSame('Ana Gómez', $pedido->nombre_cliente);
    }

    public function test_customer_name_longer_than_60_characters_is_rejected(): void
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier);

        $this->expectException(ValidationException::class);

        $service->setCustomerName($pedido, str_repeat('a', 61), $this->cashier);
    }

    public function test_customer_name_cannot_be_edited_on_a_charged_order(): void
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier);
        $pedido->update(['estado_comercial' => EstadoComercialPedido::COBRADO]);

        $this->expectException(ValidationException::class);

        $service->setCustomerName($pedido->fresh(), 'Cliente tardío', $this->cashier);
    }

    public function test_empty_draft_is_deleted_with_its_audit_events_and_releases_the_code(): void
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::MESA, $this->cashier, $this->table->getKey());

        $codigo = $pedido->codigo_corto;

        $this->assertDatabaseHas('evento_auditorias', [
            'entidad_tipo' => Pedido::class,
            'entidad_id' => $pedido->getKey(),
        ]);

        $this->assertTrue($service->discardEmptyDraft($pedido, $this->cashier));

        $this->assertDatabaseMissing('pedidos', ['id' => $pedido->getKey()]);
        $this->assertSame(0, EventoAuditoria::query()
            ->where('entidad_tipo', Pedido::class)
            ->where('entidad_id', $pedido->getKey())
            ->count());
        $this->assertSame(EstadoMesa::LIBRE, $this->table->fresh()->estado);

        $siguiente = $service->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier);
        $this->assertSame($codigo, $siguiente->codigo_corto);
    }

    public function test_draft_with_active_lines_is_not_deleted(): void
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier);
        $service->addProduct($pedido, $this->product, $this->cashier);

        $this->assertFalse($service->discardEmptyDraft($pedido->fresh(), $this->cashier));
        $this->assertDatabaseHas('pedidos', [
            'id' => $pedido->getKey(),
            'estado_comercial' => EstadoComercialPedido::ABIERTO->value,
        ]);
    }

    public function test_order_entry_page_saves_optional_customer_name(): void
    {
        $pedido = app(PedidoService::class)->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier);

        Livewire::test(OrderEntry::class, ['pedido' => $pedido])
            ->assertSet('nombreCliente', '')
            ->call('saveCustomerName', 'Lucía Martínez')
            ->assertSet('nombreCliente', 'Lucía Martínez');

        $this->assertSame('Lucía Martínez', $pedido->fresh()->nombre_cliente);
    }

    public function test_order_entry_page_can_clear_customer_name(): void
    {
        $pedido = app(PedidoService::class)->startOrder(
            TipoPedido::PARA_LLEVAR,
            $this->cashier,
            null,
            \App\Enums\OrigenPedido::CAJA,
            'Cliente temporal',
        );

        Livewire::test(OrderEntry::class, ['pedido' => $pedido])
            ->assertSet('nombreCliente', 'Cliente temporal')
            ->call('saveCustomerName', '')
            ->assertSet('nombreCliente', '');

        $this->assertNull($pedido->fresh()->nombre_cliente);
    }
}
