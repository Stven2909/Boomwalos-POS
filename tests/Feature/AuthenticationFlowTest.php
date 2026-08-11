<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Cash\CloseSession;
use App\Filament\Pages\Cash\OpenSession;
use App\Filament\Pages\Pos\ServiceSelection;
use App\Filament\Resources\Mesas\MesaResource;
use App\Models\Establecimiento;
use App\Models\SesionCaja;
use App\Models\User;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class AuthenticationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_login_screen_exposes_cashier_and_admin_access(): void
    {
        $response = $this->get('/admin/login');

        $response
            ->assertSuccessful()
            ->assertSee('Acceso administrador')
            ->assertSee('Código de cajero')
            ->assertSee('Teclado numérico');
    }

    public function test_only_an_administrator_can_manage_users(): void
    {
        $this->seed(RolesPermissionsSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        $cashier = User::factory()->create();
        $cashier->assignRole('cajero');

        $this->assertTrue(Gate::forUser($admin)->allows('create', User::class));
        $this->assertFalse(Gate::forUser($cashier)->allows('create', User::class));

        $this->actingAs($cashier)
            ->get('/admin/users')
            ->assertForbidden();

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertSuccessful();
    }

    public function test_only_an_administrator_can_manage_tables(): void
    {
        $this->seed(RolesPermissionsSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        $cashier = User::factory()->create();
        $cashier->assignRole('cajero');

        $this->assertTrue($admin->can('gestionar_mesas'));
        $this->assertFalse($cashier->can('gestionar_mesas'));

        $this->actingAs($cashier)
            ->get(MesaResource::getUrl())
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(MesaResource::getUrl())
            ->assertSuccessful();
    }

    public function test_numeric_cashier_credentials_and_admin_credentials_are_supported(): void
    {
        $this->seed(RolesPermissionsSeeder::class);

        $admin = User::factory()->create([
            'email' => 'admin@example.test',
            'password' => 'admin-password',
        ]);
        $admin->assignRole('administrador');

        $cashier = User::factory()->create([
            'usuario' => '98',
            'password' => '1234',
        ]);
        $cashier->assignRole('cajero');

        $this->assertTrue(Auth::guard('web')->attempt([
            'email' => 'admin@example.test',
            'password' => 'admin-password',
        ]));
        Auth::guard('web')->logout();

        $this->assertTrue(Auth::guard('web')->attempt([
            'usuario' => '98',
            'password' => '1234',
        ]));
        Auth::guard('web')->logout();
    }

    public function test_the_initial_seeder_creates_the_administrator_and_cashier(): void
    {
        $this->seed([RolesPermissionsSeeder::class, DemoUsersSeeder::class]);

        $this->assertDatabaseCount('usuarios', 2);
        $this->assertDatabaseHas('usuarios', [
            'email' => 'admin@example.test',
            'usuario' => 'admin',
        ]);
        $this->assertDatabaseHas('usuarios', [
            'email' => 'cashier@example.test',
            'usuario' => '98',
        ]);

        $this->assertTrue(Auth::guard('web')->attempt([
            'email' => 'admin@example.test',
            'password' => 'testing-admin-password',
        ]));
        Auth::guard('web')->logout();

        $this->assertTrue(Auth::guard('web')->attempt([
            'usuario' => '98',
            'password' => 'testing-cashier-pin',
        ]));
    }

    public function test_cashier_without_an_active_session_is_sent_to_open_session_with_banner(): void
    {
        $this->seed(RolesPermissionsSeeder::class);

        $cashier = User::factory()->create([
            'usuario' => '98',
            'password' => '1234',
        ]);
        $cashier->assignRole('cajero');

        Establecimiento::create([
            'nombre' => 'Los Boomwalos',
            'direccion' => 'Dirección de prueba',
        ]);

        Livewire::test(Login::class)
            ->set('mode', 'cashier')
            ->call('authenticateCashier', '98', '1234')
            ->assertHasNoErrors()
            ->assertRedirect(OpenSession::getUrl());

        $this->assertTrue(session()->has('turno_cerrado'));
    }

    public function test_cashier_with_an_active_session_is_sent_to_the_point_of_sale(): void
    {
        $this->seed(RolesPermissionsSeeder::class);

        $cashier = User::factory()->create([
            'usuario' => '98',
            'password' => '1234',
        ]);
        $cashier->assignRole('cajero');

        $establishment = Establecimiento::create([
            'nombre' => 'Los Boomwalos',
            'direccion' => 'Dirección de prueba',
        ]);

        SesionCaja::create([
            'establecimiento_id' => $establishment->getKey(),
            'usuario_apertura_id' => $cashier->getKey(),
            'monto_inicial' => 50,
            'fecha_apertura' => now(),
        ]);

        Livewire::test(Login::class)
            ->set('mode', 'cashier')
            ->call('authenticateCashier', '98', '1234')
            ->assertHasNoErrors()
            ->assertRedirect(ServiceSelection::getUrl());

        $this->assertFalse(session()->has('turno_cerrado'));
    }

    public function test_administrator_is_sent_to_the_cash_closing_page(): void
    {
        $this->seed(RolesPermissionsSeeder::class);

        $admin = User::factory()->create([
            'email' => 'admin@example.test',
            'password' => 'admin-password',
        ]);
        $admin->assignRole('administrador');

        Establecimiento::create([
            'nombre' => 'Los Boomwalos',
            'direccion' => 'Dirección de prueba',
        ]);

        Livewire::test(Login::class)
            ->set('mode', 'admin')
            ->set('email', 'admin@example.test')
            ->set('password', 'admin-password')
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect(CloseSession::getUrl());

        $this->assertTrue(session()->has('turno_cerrado'));
    }
}
