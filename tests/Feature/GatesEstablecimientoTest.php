<?php

namespace Tests\Feature;

use App\Filament\Pages\EstablishmentSelection;
use App\Filament\Pages\Kitchen\KitchenDisplay;
use App\Filament\Pages\Pos\EntregaDisplay;
use App\Filament\Pages\Pos\ServiceSelection;
use App\Models\Establecimiento;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Los guardas de sucursal activa en cocina y entrega: sin sucursal activa y
 * con varias accesibles se redirige a la selección; sin ninguna se deniega.
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

    public function test_kitchen_sin_sucursal_activa_y_con_varias_redirige_a_la_seleccion(): void
    {
        $first = Establecimiento::create(['nombre' => 'Centro', 'direccion' => 'Centro']);
        $second = Establecimiento::create(['nombre' => 'Norte', 'direccion' => 'Norte']);
        $user = $this->operador();
        $user->establecimientos()->attach([$first, $second]);

        $this->actingAs($user);

        Livewire::test(KitchenDisplay::class)
            ->assertRedirect(EstablishmentSelection::getUrl());
    }

    public function test_kitchen_sin_sucursales_accesibles_deniega_con_403(): void
    {
        $user = $this->operador();

        $this->actingAs($user);

        try {
            (new KitchenDisplay())->mount();
            $this->fail('Se esperaba un 403.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_entrega_sin_sucursal_activa_y_con_varias_redirige_a_la_seleccion(): void
    {
        $first = Establecimiento::create(['nombre' => 'Centro', 'direccion' => 'Centro']);
        $second = Establecimiento::create(['nombre' => 'Norte', 'direccion' => 'Norte']);
        $user = $this->operador();
        $user->establecimientos()->attach([$first, $second]);

        $this->actingAs($user);

        Livewire::test(EntregaDisplay::class)
            ->assertRedirect(EstablishmentSelection::getUrl());
    }

    public function test_entrega_sin_sucursales_accesibles_deniega_con_403(): void
    {
        $user = $this->operador();

        $this->actingAs($user);

        try {
            (new EntregaDisplay())->mount();
            $this->fail('Se esperaba un 403.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_simbolo_moneda_sin_sucursal_devuelve_dolar(): void
    {
        $user = $this->operador();
        $this->actingAs($user);

        $this->assertSame('$', app(ServiceSelection::class)->getSimboloMonedaProperty());
    }
}
