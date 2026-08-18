<?php

namespace Tests\Feature;

use App\Filament\Pages\Pos\ServiceSelection;
use App\Filament\Pages\Printing\PrintMonitor;
use App\Filament\Resources\Impresoras\ImpresoraResource;
use App\Models\Establecimiento;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Los guardas de sucursal activa en módulos de ajustes: sin permisos se
 * deniega; con permisos se permite el acceso.
 */
class GatesEstablecimientoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermissionsSeeder::class);
    }

    private function operador(): User
    {
        $user = User::factory()->create();
        $user->assignRole('cajero');

        return $user;
    }

    public function test_print_monitor_requires_ver_impresoras_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(PrintMonitor::getUrl())
            ->assertForbidden();
    }

    public function test_print_monitor_is_accessible_with_permission(): void
    {
        $user = $this->operador();

        $this->actingAs($user)
            ->get(PrintMonitor::getUrl())
            ->assertSuccessful();
    }

    public function test_impresora_resource_requires_ver_impresoras_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(ImpresoraResource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_impresora_resource_is_accessible_with_permission(): void
    {
        $user = $this->operador();

        $this->actingAs($user)
            ->get(ImpresoraResource::getUrl('index'))
            ->assertSuccessful();
    }

    public function test_simbolo_moneda_sin_sucursal_devuelve_dolar(): void
    {
        $user = $this->operador();
        $this->actingAs($user);

        $this->assertSame('$', app(ServiceSelection::class)->getSimboloMonedaProperty());
    }
}
