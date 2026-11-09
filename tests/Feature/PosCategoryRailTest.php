<?php

namespace Tests\Feature;

use App\Contracts\EstablishmentContextInterface;
use App\Enums\EstadoMesa;
use App\Enums\TipoPedido;
use App\Filament\Pages\Pos\OrderEntry;
use App\Models\Categoria;
use App\Models\Establecimiento;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\SesionCaja;
use App\Models\User;
use App\Services\PedidoService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PosCategoryRailTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Establecimiento $establishment;

    private Pedido $pedido;

    private Categoria $especialidades;

    private Categoria $refrescate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesPermissionsSeeder::class);

        $this->cashier = User::factory()->create([
            'usuario' => '43',
            'password' => '1234',
        ]);
        $this->cashier->assignRole('cajero');

        $this->establishment = Establecimiento::create([
            'nombre' => 'Pupusería Categorías',
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

        $this->especialidades = Categoria::create([
            'nombre' => 'Especialidades Time',
            'establecimiento_id' => $this->establishment->getKey(),
        ]);

        $this->refrescate = Categoria::create([
            'nombre' => 'Refrescate / Limonadas',
            'establecimiento_id' => $this->establishment->getKey(),
        ]);

        $table = Mesa::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'numero' => '13',
            'zona' => 'SALON',
            'estado' => EstadoMesa::LIBRE,
        ]);

        $this->pedido = app(PedidoService::class)->startOrder(
            TipoPedido::MESA,
            $this->cashier,
            $table->getKey(),
        );
    }

    public function test_el_catalogo_no_muestra_la_pestana_todo(): void
    {
        Livewire::test(OrderEntry::class, ['pedido' => $this->pedido])
            ->assertDontSee('Todo')
            ->assertSee('Especialidades Time')
            ->assertSee('Refrescate / Limonadas');
    }

    public function test_tocar_la_categoria_activa_vuelve_al_catalogo_completo(): void
    {
        Livewire::test(OrderEntry::class, ['pedido' => $this->pedido])
            ->call('selectCategory', (string) $this->especialidades->getKey())
            ->assertSet('category', (string) $this->especialidades->getKey())
            ->call('selectCategory', (string) $this->especialidades->getKey())
            ->assertSet('category', 'all');
    }
}
