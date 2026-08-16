<?php

namespace Tests\Feature;

use App\Contracts\EstablishmentContextInterface;
use App\Enums\ZonaMesa;
use App\Filament\Pages\Cash\OpenSession;
use App\Filament\Pages\EstablishmentSelection;
use App\Filament\Pages\Pos\ListaPedidos;
use App\Filament\Pages\Pos\ServiceSelection;
use App\Filament\Resources\Mesas\Pages\ManageMesas;
use App\Models\Establecimiento;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermissionsSeeder::class);
    }

    public function test_operator_can_only_select_an_assigned_branch(): void
    {
        $first = Establecimiento::create(['nombre' => 'Sucursal Centro', 'direccion' => 'Centro']);
        $second = Establecimiento::create(['nombre' => 'Sucursal Norte', 'direccion' => 'Norte']);
        $cashier = User::factory()->create();
        $cashier->assignRole('cajero');
        $cashier->establecimientos()->attach($first);

        $this->actingAs($cashier);
        $context = app(EstablishmentContextInterface::class);

        $this->assertTrue($context->canAccess((int) $first->getKey()));
        $this->assertFalse($context->canAccess((int) $second->getKey()));

        $this->expectException(AuthorizationException::class);
        $context->set((int) $second->getKey());
    }

    public function test_company_administrator_can_select_any_branch(): void
    {
        $first = Establecimiento::create(['nombre' => 'Sucursal Centro', 'direccion' => 'Centro']);
        $second = Establecimiento::create(['nombre' => 'Sucursal Norte', 'direccion' => 'Norte']);
        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        $this->actingAs($admin);
        $selected = app(EstablishmentContextInterface::class)->set((int) $second->getKey());

        $this->assertSame($second->getKey(), $selected->getKey());
        $this->assertSame($second->getKey(), app(EstablishmentContextInterface::class)->id());
    }

    public function test_operator_without_assignment_has_null_optional_id(): void
    {
        Establecimiento::create(['nombre' => 'Sucursal Centro', 'direccion' => 'Centro']);
        Establecimiento::create(['nombre' => 'Sucursal Norte', 'direccion' => 'Norte']);
        $cashier = User::factory()->create();
        $cashier->assignRole('cajero');

        $this->actingAs($cashier);
        $context = app(EstablishmentContextInterface::class);

        $this->assertNull($context->idOrNull());
        $this->assertNull($context->currentOrNull());

        $this->expectException(ValidationException::class);
        $context->id();
    }

    public function test_operator_with_two_branches_must_select_one(): void
    {
        $first = Establecimiento::create(['nombre' => 'Sucursal Centro', 'direccion' => 'Centro']);
        $second = Establecimiento::create(['nombre' => 'Sucursal Norte', 'direccion' => 'Norte']);
        $cashier = User::factory()->create();
        $cashier->assignRole('cajero');
        $cashier->establecimientos()->attach([$first, $second]);

        $this->actingAs($cashier);
        $context = app(EstablishmentContextInterface::class);

        $this->assertNull($context->idOrNull());

        $context->set((int) $second->getKey());
        $this->assertSame($second->getKey(), $context->id());
        $this->assertSame($second->getKey(), $context->currentOrNull()?->getKey());
    }

    public function test_operator_with_single_branch_auto_resolves(): void
    {
        $establishment = Establecimiento::create(['nombre' => 'Sucursal Centro', 'direccion' => 'Centro']);
        $cashier = User::factory()->create();
        $cashier->assignRole('cajero');

        $this->actingAs($cashier);
        $context = app(EstablishmentContextInterface::class);

        $this->assertSame($establishment->getKey(), $context->idOrNull());
        $this->assertSame($establishment->getKey(), $context->id());
    }

    public function test_stale_unauthorized_session_does_not_leak_other_branch_data(): void
    {
        $authorized = Establecimiento::create(['nombre' => 'Sucursal Centro', 'direccion' => 'Centro']);
        $unauthorized = Establecimiento::create(['nombre' => 'Sucursal Norte', 'direccion' => 'Norte']);
        $cashier = User::factory()->create();
        $cashier->assignRole('cajero');
        $cashier->establecimientos()->attach($authorized);

        $this->actingAs($cashier)
            ->withSession(['pos.establishment_id' => $unauthorized->getKey()])
            ->get(ListaPedidos::getUrl())
            ->assertRedirect(OpenSession::getUrl());
    }

    public function test_lista_pedidos_without_branch_redirects_to_selection(): void
    {
        $first = Establecimiento::create(['nombre' => 'Sucursal Centro', 'direccion' => 'Centro']);
        $second = Establecimiento::create(['nombre' => 'Sucursal Norte', 'direccion' => 'Norte']);
        $cashier = User::factory()->create();
        $cashier->assignRole('cajero');
        $cashier->establecimientos()->attach([$first, $second]);

        $this->actingAs($cashier);

        Livewire::test(ListaPedidos::class)
            ->assertRedirect(EstablishmentSelection::getUrl());
    }

    public function test_service_selection_without_branch_redirects_to_selection(): void
    {
        $first = Establecimiento::create(['nombre' => 'Sucursal Centro', 'direccion' => 'Centro']);
        $second = Establecimiento::create(['nombre' => 'Sucursal Norte', 'direccion' => 'Norte']);
        $cashier = User::factory()->create();
        $cashier->assignRole('cajero');
        $cashier->establecimientos()->attach([$first, $second]);

        $this->actingAs($cashier);

        Livewire::test(ServiceSelection::class)
            ->assertRedirect(EstablishmentSelection::getUrl());
    }

    public function test_lista_pedidos_without_assigned_branch_is_denied(): void
    {
        Establecimiento::create(['nombre' => 'Sucursal Centro', 'direccion' => 'Centro']);
        Establecimiento::create(['nombre' => 'Sucursal Norte', 'direccion' => 'Norte']);
        $cashier = User::factory()->create();
        $cashier->assignRole('cajero');

        $this->actingAs($cashier)
            ->get(ListaPedidos::getUrl())
            ->assertForbidden();
    }

    public function test_open_session_without_branch_fails_controlled(): void
    {
        Establecimiento::create(['nombre' => 'Sucursal Centro', 'direccion' => 'Centro']);
        Establecimiento::create(['nombre' => 'Sucursal Norte', 'direccion' => 'Norte']);
        $cashier = User::factory()->create();
        $cashier->assignRole('cajero');

        $this->actingAs($cashier);

        Livewire::test(OpenSession::class)
            ->set('montoInicial', '100.00')
            ->call('openSession')
            ->assertHasErrors(['establecimiento' => 'Configura un establecimiento antes de abrir el turno.']);
    }

    public function test_manage_mesas_without_branch_cannot_create(): void
    {
        Establecimiento::create(['nombre' => 'Sucursal Centro', 'direccion' => 'Centro']);
        Establecimiento::create(['nombre' => 'Sucursal Norte', 'direccion' => 'Norte']);
        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        $this->actingAs($admin);

        Livewire::test(ManageMesas::class)
            ->callAction('create', data: [
                'numero' => '99',
                'zona' => ZonaMesa::SALON->value,
                'activa' => true,
            ])
            ->assertHasErrors(['establecimiento' => 'Selecciona la sucursal en la que deseas trabajar.']);

        $this->assertDatabaseCount('mesas', 0);
    }
}
