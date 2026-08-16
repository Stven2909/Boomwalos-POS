<?php

namespace Tests\Feature;

use App\Contracts\EstablishmentContextInterface;
use App\Models\Establecimiento;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
