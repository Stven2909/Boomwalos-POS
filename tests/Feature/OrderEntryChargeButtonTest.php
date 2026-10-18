<?php

namespace Tests\Feature;

use App\Contracts\EstablishmentContextInterface;
use App\Enums\EstadoComercialPedido;
use App\Enums\EstadoMesa;
use App\Enums\MetodoPago;
use App\Enums\TipoPedido;
use App\Enums\ZonaMesa;
use App\Filament\Pages\Pos\ChargeOrder;
use App\Filament\Pages\Pos\OrderEntry;
use App\Models\Categoria;
use App\Models\Establecimiento;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\SesionCaja;
use App\Models\User;
use App\Services\CobroService;
use App\Services\PedidoService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderEntryChargeButtonTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Establecimiento $establishment;

    private Mesa $table;

    private Producto $product;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->seed(RolesPermissionsSeeder::class);

        $this->cashier = User::factory()->create([
            'usuario' => '41',
            'password' => '1234',
        ]);
        $this->cashier->assignRole('cajero');

        $this->establishment = Establecimiento::create([
            'nombre' => 'Pupusería Botón Cobro',
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
            'numero' => '12',
            'zona' => ZonaMesa::SALON,
            'estado' => EstadoMesa::LIBRE,
        ]);

        $category = Categoria::create(['nombre' => 'Bebidas']);
        $this->product = Producto::create([
            'categoria_id' => $category->getKey(),
            'nombre' => 'Jugo de naranja',
            'precio' => 3,
            'disponibilidad' => 'DISPONIBLE',
        ]);
    }

    public function test_pedido_pendiente_de_cobro_ofrece_ir_al_cobro(): void
    {
        $pedido = $this->mesaPendienteDeCobro();

        $component = Livewire::test(OrderEntry::class, ['pedido' => $pedido]);

        $component->assertSet('canGoToCharge', true)
            ->call('goToCharge')
            ->assertRedirect(ChargeOrder::getUrl(['pedido' => $pedido->getKey()]));
    }

    public function test_pedido_ya_cobrado_no_ofrece_ir_al_cobro(): void
    {
        $pedido = $this->mesaPendienteDeCobro();

        app(CobroService::class)->charge($pedido, MetodoPago::EFECTIVO, '3.00', $this->cashier);

        $this->assertSame(EstadoComercialPedido::CERRADO, $pedido->fresh()->estado_comercial);

        Livewire::test(OrderEntry::class, ['pedido' => $pedido->fresh()])
            ->assertSet('canGoToCharge', false);
    }

    public function test_usuario_sin_permiso_de_cobro_no_ve_el_boton(): void
    {
        $pedido = $this->mesaPendienteDeCobro();

        $sinCobro = Role::firstOrCreate(['name' => 'solo_entrada', 'guard_name' => 'web']);
        $sinCobro->syncPermissions(['crear_pedido']);

        $cocinero = User::factory()->create([
            'usuario' => '42',
            'password' => '1234',
        ]);
        $cocinero->assignRole('solo_entrada');
        $cocinero->establecimientos()->attach($this->establishment->getKey());

        $this->actingAs($cocinero);

        Livewire::test(OrderEntry::class, ['pedido' => $pedido])
            ->assertSet('canGoToCharge', false);
    }

    public function test_pedido_abierto_no_ofrece_ir_al_cobro(): void
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::MESA, $this->cashier, $this->table->getKey());
        $service->addProduct($pedido, $this->product, $this->cashier);

        $this->assertSame(EstadoComercialPedido::ABIERTO, $pedido->fresh()->estado_comercial);

        Livewire::test(OrderEntry::class, ['pedido' => $pedido])
            ->assertSet('canGoToCharge', false);
    }

    private function mesaPendienteDeCobro(): Pedido
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::MESA, $this->cashier, $this->table->getKey());
        $service->addProduct($pedido, $this->product, $this->cashier);
        $service->sendPendingBatch($pedido, $this->cashier);
        $service->sendToCashRegister($pedido, $this->cashier);

        $this->assertSame(EstadoComercialPedido::PENDIENTE_COBRO, $pedido->fresh()->estado_comercial);

        return $pedido->fresh();
    }
}
