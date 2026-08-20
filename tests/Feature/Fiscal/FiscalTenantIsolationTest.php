<?php

namespace Tests\Feature\Fiscal;

use App\Application\Fiscal\HmacSigner;
use App\Enums\EstadoComercialPedido;
use App\Enums\EstadoVentaFiscal;
use App\Enums\TipoPedido;
use App\Models\Establecimiento;
use App\Models\Pedido;
use App\Models\User;
use App\Models\VentaFiscalPos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Traits\TenantDatabaseHarness;
use Tests\TestCase;

/**
 * Aislamiento REAL de datos entre empresas en modo `database`, con dos bases
 * SQLite operativas separadas y el registro de plataforma en otro archivo.
 */
class FiscalTenantIsolationTest extends TestCase
{
    use RefreshDatabase;
    use TenantDatabaseHarness;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantHarnessSetUp();
        $this->tenantHarnessMigratePlatform();
        $this->tenantHarnessMigrateOperative($this->tenantHarnessFiles['acme']);
        $this->tenantHarnessMigrateOperative($this->tenantHarnessFiles['beta']);
        $this->tenantHarnessSeedPlatform();
    }

    protected function tearDown(): void
    {
        DB::setDefaultConnection(config('tenancy.fallback_connection', config('database.default')));
        $this->tenantHarnessTearDown();

        parent::tearDown();
    }

    private function ventaEnTenant(string $slug, string $fiscalSaleId): int
    {
        $this->tenantHarnessUse($slug);

        $establishment = Establecimiento::create([
            'nombre' => ucfirst($slug).' Centro',
            'direccion' => 'Centro',
        ]);

        $usuario = User::factory()->create();

        $pedido = Pedido::create([
            'numero_seguimiento' => 'TRK-'.mb_strtoupper($slug),
            'tipo_pedido' => TipoPedido::PARA_LLEVAR,
            'establecimiento_id' => $establishment->getKey(),
            'usuario_id' => $usuario->getKey(),
            'estado_comercial' => EstadoComercialPedido::ABIERTO,
        ]);

        VentaFiscalPos::create([
            'establecimiento_id' => $establishment->getKey(),
            'pedido_id' => $pedido->getKey(),
            'referencia' => 'P-'.$slug,
            'monto_total' => '4.00',
            'metodo_pago' => 'EFECTIVO',
            'fiscal_sale_id' => $fiscalSaleId,
            'estado' => EstadoVentaFiscal::NO,
        ]);

        return (int) $establishment->getKey();
    }

    private function postWebhook(string $host, array $datos): TestResponse
    {
        $content = json_encode($datos);
        $path = '/api/fiscal/v1/webhooks';
        $timestamp = time();
        $nonce = 'test-nonce-iso-123';
        $secret = (string) config('fiscal.mock.secret');

        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CLIENT_ID' => 'est-test',
            'HTTP_X_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_NONCE' => $nonce,
            'HTTP_X_SIGNATURE' => HmacSigner::sign('POST', $path, (int) $timestamp, $nonce, (string) $content, $secret),
        ];

        return $this->call('POST', 'http://'.$host.$path, [], [], [], $server, $content);
    }

    public function test_webhook_de_acme_escribe_solo_en_la_base_de_acme(): void
    {
        $acmeEstablishmentId = $this->ventaEnTenant('acme', 'SALE-ACME');
        $this->ventaEnTenant('beta', 'SALE-BETA');

        $this->postWebhook('acme.pos.localhost', [
            'secuencia' => 1,
            'tipo' => 'DTE_EMITIDO',
            'fiscal_sale_id' => 'SALE-ACME',
            'payload' => [],
        ])->assertStatus(202);

        $this->assertSame(1, $this->tenantHarnessCount($this->tenantHarnessFiles['acme'], 'webhook_events_pos'));
        $this->assertSame(0, $this->tenantHarnessCount($this->tenantHarnessFiles['beta'], 'webhook_events_pos'));

        $this->tenantHarnessPointTo($this->tenantHarnessFiles['acme']);
        $this->assertSame(
            $acmeEstablishmentId,
            (int) DB::connection('tenant')->table('webhook_events_pos')->value('establecimiento_id')
        );
    }

    public function test_webhook_de_beta_escribe_solo_en_la_base_de_beta(): void
    {
        $this->ventaEnTenant('acme', 'SALE-ACME');
        $betaEstablishmentId = $this->ventaEnTenant('beta', 'SALE-BETA');

        $this->postWebhook('beta.pos.localhost', [
            'secuencia' => 1,
            'tipo' => 'DTE_EMITIDO',
            'fiscal_sale_id' => 'SALE-BETA',
            'payload' => [],
        ])->assertStatus(202);

        $this->assertSame(0, $this->tenantHarnessCount($this->tenantHarnessFiles['acme'], 'webhook_events_pos'));
        $this->assertSame(1, $this->tenantHarnessCount($this->tenantHarnessFiles['beta'], 'webhook_events_pos'));

        $this->tenantHarnessPointTo($this->tenantHarnessFiles['beta']);
        $this->assertSame(
            $betaEstablishmentId,
            (int) DB::connection('tenant')->table('webhook_events_pos')->value('establecimiento_id')
        );
    }
}
