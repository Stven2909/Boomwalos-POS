<?php

namespace Tests\Feature\Portal;

use App\Enums\EstadoComercialPedido;
use App\Enums\EstadoDocumentoFiscal;
use App\Enums\OrigenPedido;
use App\Enums\TipoDocumento;
use App\Enums\TipoPedido;
use App\Models\DocumentoFiscal;
use App\Models\Establecimiento;
use App\Models\Pedido;
use App\Models\User;
use App\Services\Portal\PortalAdminTokenService;
use App\Services\Portal\PortalFiscalService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $cashier;
    private Establecimiento $establecimiento;
    private Pedido $pedido;
    private DocumentoFiscal $solicitudPendiente;
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermissionsSeeder::class);

        $this->admin = User::factory()->create([
            'usuario' => 'admin_portal',
            'email' => 'admin@boomwalos.com',
            'password' => 'secret123',
        ]);
        $this->admin->assignRole('administrador');

        $this->cashier = User::factory()->create([
            'usuario' => 'cajero_portal',
            'email' => 'cajero@boomwalos.com',
            'password' => 'secret123',
        ]);
        $this->cashier->assignRole('cajero');

        $this->establecimiento = Establecimiento::create([
            'nombre' => 'Boomwalos Central',
            'direccion' => 'Calle Principal #123',
        ]);

        $this->pedido = Pedido::create([
            'numero_seguimiento' => 'TRACK-ADMIN-01',
            'codigo_corto' => 1,
            'fecha_codigo' => now()->toDateString(),
            'tipo_pedido' => TipoPedido::MESA,
            'establecimiento_id' => $this->establecimiento->getKey(),
            'usuario_id' => $this->admin->getKey(),
            'origen_pedido' => OrigenPedido::CAJA,
            'estado_comercial' => EstadoComercialPedido::COBRADO,
        ]);

        $this->solicitudPendiente = DocumentoFiscal::create([
            'pedido_id' => $this->pedido->getKey(),
            'tipo_documento' => TipoDocumento::CCF,
            'estado' => EstadoDocumentoFiscal::PENDIENTE,
            'datos_solicitante' => [
                'nombre' => 'Cliente Corporativo',
                'nit' => '0614-010101-100-0',
                'nrc' => '99999-9',
                'giro' => 'Servicios',
            ],
            'solicitado_at' => now(),
        ]);

        $this->adminToken = app(PortalAdminTokenService::class)->generateToken($this->admin);
    }

    public function test_admin_login_success(): void
    {
        $response = $this->postJson('/api/v1/portal-admin/login', [
            'usuario' => 'admin_portal',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.usuario', 'admin_portal')
            ->assertJsonStructure(['token', 'modo_emision']);
    }

    public function test_admin_login_fails_for_invalid_password(): void
    {
        $response = $this->postJson('/api/v1/portal-admin/login', [
            'usuario' => 'admin_portal',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_admin_login_fails_for_non_admin_role(): void
    {
        $response = $this->postJson('/api/v1/portal-admin/login', [
            'usuario' => 'cajero_portal',
            'password' => 'secret123',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_protected_endpoints_require_valid_token(): void
    {
        $this->getJson('/api/v1/portal-admin/solicitudes')
            ->assertStatus(401);

        $this->withToken('token-falso-invalido')
            ->getJson('/api/v1/portal-admin/solicitudes')
            ->assertStatus(401);
    }

    public function test_admin_can_list_solicitudes_and_filter_by_state(): void
    {
        $response = $this->withToken($this->adminToken)
            ->getJson('/api/v1/portal-admin/solicitudes?estado=PENDIENTE');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('stats.pendientes', 1);
    }

    public function test_admin_can_update_customer_data_in_solicitud(): void
    {
        $response = $this->withToken($this->adminToken)
            ->putJson("/api/v1/portal-admin/solicitudes/{$this->solicitudPendiente->getKey()}", [
                'nrc' => '123456-7',
                'giro' => 'Restaurantes y Cafeterías',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->solicitudPendiente->refresh();
        $this->assertSame('123456-7', $this->solicitudPendiente->datos_solicitante['nrc'] ?? null);
        $this->assertSame('Restaurantes y Cafeterías', $this->solicitudPendiente->datos_solicitante['giro'] ?? null);
    }

    public function test_admin_can_generate_dte_manually(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson("/api/v1/portal-admin/solicitudes/{$this->solicitudPendiente->getKey()}/generar");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('estado', 'EMITIDO')
            ->assertJsonStructure(['dte' => ['codigo_generacion', 'sello_recepcion']]);

        $this->solicitudPendiente->refresh();
        $this->assertSame(EstadoDocumentoFiscal::EMITIDO, $this->solicitudPendiente->estado);
        $this->assertNotNull($this->solicitudPendiente->codigo_generacion);
    }

    public function test_admin_can_reject_solicitud(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson("/api/v1/portal-admin/solicitudes/{$this->solicitudPendiente->getKey()}/rechazar", [
                'motivo' => 'NRC inválido según base de Hacienda',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->solicitudPendiente->refresh();
        $this->assertSame(EstadoDocumentoFiscal::RECHAZADO, $this->solicitudPendiente->estado);
        $this->assertSame('NRC inválido según base de Hacienda', $this->solicitudPendiente->datos_solicitante['motivo_rechazo'] ?? null);
    }

    public function test_admin_can_get_and_update_portal_emission_mode(): void
    {
        // 1. Obtener configuración inicial
        $resGet = $this->withToken($this->adminToken)
            ->getJson('/api/v1/portal-admin/configuracion');

        $resGet->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['modo_emision', 'modos_disponibles']]);

        // 2. Cambiar modo a AUTOMATICO
        $resPut = $this->withToken($this->adminToken)
            ->putJson('/api/v1/portal-admin/configuracion', [
                'modo_emision' => 'AUTOMATICO',
            ]);

        $resPut->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.modo_emision', 'AUTOMATICO');

        $this->assertSame('AUTOMATICO', app(PortalFiscalService::class)->obtenerModoEmision());
    }
}
