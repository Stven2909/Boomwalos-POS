<?php

namespace Tests\Feature;

use App\Models\Establecimiento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Traits\TenantDatabaseHarness;
use Tests\TestCase;

/**
 * El route-model binding de `establishment.context` debe resolverse dentro de
 * la base operativa del tenant del host: la sucursal de Acme existe en la base
 * de Acme y la de Beta en la de Beta, cada una invisible para la otra.
 */
class EstablishmentBindingTest extends TestCase
{
    use RefreshDatabase;
    use TenantDatabaseHarness;

    private User $userA;

    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantHarnessSetUp();
        $this->tenantHarnessMigratePlatform();
        $this->tenantHarnessMigrateOperative($this->tenantHarnessFiles['acme']);
        $this->tenantHarnessMigrateOperative($this->tenantHarnessFiles['beta']);
        $this->tenantHarnessSeedPlatform();

        $this->tenantHarnessUse('acme');
        $this->userA = User::factory()->create(['id' => 2001]);
        $establishmentA = Establecimiento::forceCreate(['id' => 1001, 'nombre' => 'Acme Centro', 'direccion' => 'Centro']);
        $this->userA->establecimientos()->attach($establishmentA);

        $this->tenantHarnessUse('beta');
        $this->userB = User::factory()->create(['id' => 2002]);
        $establishmentB = Establecimiento::forceCreate(['id' => 1002, 'nombre' => 'Beta Norte', 'direccion' => 'Norte']);
        $this->userB->establecimientos()->attach($establishmentB);
    }

    protected function tearDown(): void
    {
        DB::setDefaultConnection(config('tenancy.fallback_connection', config('database.default')));
        $this->tenantHarnessTearDown();

        parent::tearDown();
    }

    private function postContext(string $host, int $establecimiento): \Illuminate\Testing\TestResponse
    {
        return $this->call(
            'POST',
            'http://'.$host.'/admin/context/establishment/'.$establecimiento
        );
    }

    public function test_binding_de_sucursal_de_acme_se_resuelve_en_la_base_de_acme(): void
    {
        $this->actingAs($this->userA);

        $response = $this->postContext('acme.pos.localhost', 1001);

        $response->assertRedirect();
        $this->assertSame('Sucursal activa actualizada.', session('status'));
    }

    public function test_binding_de_sucursal_de_beta_devuelve_404_en_host_de_acme(): void
    {
        $this->actingAs($this->userA);

        $this->postContext('acme.pos.localhost', 1002)->assertNotFound();
    }

    public function test_binding_de_sucursal_de_beta_se_resuelve_en_la_base_de_beta(): void
    {
        $this->actingAs($this->userB);

        $response = $this->postContext('beta.pos.localhost', 1002);

        $response->assertRedirect();
        $this->assertSame('Sucursal activa actualizada.', session('status'));
    }

    public function test_binding_de_sucursal_de_acme_devuelve_404_en_host_de_beta(): void
    {
        $this->actingAs($this->userB);

        $this->postContext('beta.pos.localhost', 1001)->assertNotFound();
    }
}
