<?php

namespace Tests\Feature;

use App\Context\TenantContext;
use App\Filament\Pages\BrandSettings;
use App\Models\Platform\PlatformTenant;
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

    private PlatformTenant $tenant;

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

        $this->tenant = PlatformTenant::create([
            'slug' => config('tenancy.default_slug', 'demo'),
            'display_name' => 'Pupusería Demo',
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

    public function test_admin_can_save_brand_settings(): void
    {
        $this->actingAs($this->admin);

        app(TenantContext::class)->set($this->tenant);

        Livewire::test(BrandSettings::class)
            ->set('displayName', 'Pupusería Central')
            ->set('primaryColor', '#123456')
            ->set('secondaryColor', '#654321')
            ->set('contactEmail', 'hola@example.test')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('platform_tenants', [
            'slug' => config('tenancy.default_slug', 'demo'),
            'display_name' => 'Pupusería Central',
            'primary_color' => '#123456',
            'secondary_color' => '#654321',
            'contact_email' => 'hola@example.test',
        ]);
    }
}
