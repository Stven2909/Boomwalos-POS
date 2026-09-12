<?php

namespace Tests\Feature;

use App\Filament\Pages\BrandSettings;
use App\Models\Configuracion;
use App\Models\Establecimiento;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BrandSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

    private Establecimiento $establishment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesPermissionsSeeder::class);

        $this->admin = User::factory()->create([
            'usuario' => '1',
            'password' => '1234',
        ]);
        $this->admin->assignRole('administrador');

        $this->cashier = User::factory()->create([
            'usuario' => '21',
            'password' => '1234',
        ]);
        $this->cashier->assignRole('cajero');

        $this->establishment = Establecimiento::create([
            'nombre' => 'Pupusería Demo',
            'direccion' => 'Centro',
        ]);
    }

    public function test_admin_can_open_brand_settings_page(): void
    {
        $this->actingAs($this->admin)
            ->get(BrandSettings::getUrl())
            ->assertOk()
            ->assertSee('Marca de la empresa');
    }

    public function test_cashier_cannot_access_brand_settings(): void
    {
        $this->actingAs($this->cashier)
            ->get(BrandSettings::getUrl())
            ->assertForbidden();
    }

    public function test_admin_can_save_brand_settings_for_active_establishment(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(BrandSettings::class)
            ->set('displayName', 'Pupusería Central')
            ->set('ticketHeader', 'Sucursal Central')
            ->set('contactEmail', 'hola@example.test')
            ->call('save')
            ->assertHasNoErrors();

        $branding = Configuracion::query()
            ->where('establecimiento_id', $this->establishment->getKey())
            ->where('clave', 'marca')
            ->value('valor');

        $this->assertSame('Pupusería Central', $branding['display_name']);
        $this->assertSame('Sucursal Central', $branding['ticket_header']);
        $this->assertSame('hola@example.test', $branding['contact_email']);
    }
}
