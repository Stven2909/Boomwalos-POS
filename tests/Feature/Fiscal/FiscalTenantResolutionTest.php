<?php

namespace Tests\Feature\Fiscal;

use App\Application\Fiscal\HmacSigner;
use App\Contracts\TenantConnectionResolverInterface;
use App\Enums\EstadoComercialPedido;
use App\Enums\EstadoVentaFiscal;
use App\Enums\TipoPedido;
use App\Models\Establecimiento;
use App\Models\Pedido;
use App\Models\Platform\PlatformTenant;
use App\Models\User;
use App\Models\VentaFiscalPos;
use App\Models\WebhookEventPos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Resolución de tenant en modo `single` (sin aislamiento real de datos).
 *
 * En single mode todas las filas comparten la misma base de datos: estas
 * pruebas verifican que el subdominio resuelve el tenant correcto y que el
 * flujo del webhook atribuye el evento al establecimiento de esa empresa.
 * El aislamiento real de datos se prueba únicamente en modo `database`.
 */
class FiscalTenantResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tenancy.mode' => 'single']);
    }

    /** @return array<string, PlatformTenant> */
    private function createTenants(): array
    {
        return [
            'acme' => PlatformTenant::create(['slug' => 'acme', 'display_name' => 'Acme POS', 'status' => 'active']),
            'beta' => PlatformTenant::create(['slug' => 'beta', 'display_name' => 'Beta POS', 'status' => 'active']),
        ];
    }

    private function postWebhook(string $host, array $datos): TestResponse
    {
        $content = json_encode($datos);
        $path = '/api/fiscal/v1/webhooks';
        $timestamp = time();
        $nonce = 'test-nonce-res-wh';
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

    private function postVenta(string $host, array $payload): TestResponse
    {
        $content = json_encode($payload);
        $path = '/api/fiscal/v1/ventas';
        $timestamp = time();
        $nonce = 'test-nonce-res-v';
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

    public function test_host_acme_resuelve_el_tenant_acme(): void
    {
        $this->createTenants();

        $resolved = app(TenantConnectionResolverInterface::class)->resolve('acme.pos.localhost');

        $this->assertNotNull($resolved);
        $this->assertSame('acme', $resolved->slug);
    }

    public function test_host_beta_resuelve_el_tenant_beta(): void
    {
        $this->createTenants();

        $resolved = app(TenantConnectionResolverInterface::class)->resolve('beta.pos.localhost');

        $this->assertNotNull($resolved);
        $this->assertSame('beta', $resolved->slug);
    }

    public function test_webhook_en_host_acme_atribuye_el_evento_al_establecimiento_de_acme(): void
    {
        $this->createTenants();
        $establishment = Establecimiento::create(['nombre' => 'Acme Centro', 'direccion' => 'Centro']);
        $usuario = User::factory()->create();

        $pedido = Pedido::create([
            'numero_seguimiento' => 'TRK-RESOLUCION-1',
            'tipo_pedido' => TipoPedido::PARA_LLEVAR,
            'establecimiento_id' => $establishment->getKey(),
            'usuario_id' => $usuario->getKey(),
            'estado_comercial' => EstadoComercialPedido::ABIERTO,
        ]);

        VentaFiscalPos::create([
            'establecimiento_id' => $establishment->getKey(),
            'pedido_id' => $pedido->getKey(),
            'referencia' => 'P-0001',
            'monto_total' => '4.00',
            'metodo_pago' => 'EFECTIVO',
            'fiscal_sale_id' => 'SALE-ACME',
            'estado' => EstadoVentaFiscal::NO,
        ]);

        $this->postWebhook('acme.pos.localhost', [
            'secuencia' => 1,
            'tipo' => 'DTE_EMITIDO',
            'fiscal_sale_id' => 'SALE-ACME',
            'payload' => [],
        ])->assertStatus(202);

        $this->assertSame(
            (int) $establishment->getKey(),
            (int) WebhookEventPos::query()->latest('id')->first()->establecimiento_id
        );
    }

    public function test_misma_clave_reintento_en_tenants_distintos_no_genera_409_cruzado(): void
    {
        $this->createTenants();

        $payloadBase = [
            'clave_reintento' => 'CLAVE-COMPARTIDA',
            'referencia' => 'P-0001',
            'fecha_emision' => '2026-08-15T12:00:00-06:00',
            'monto_total' => '4.00',
            'metodo_pago' => 'EFECTIVO',
        ];

        // Acme usa la clave con su payload; Beta usa la misma clave con OTRO
        // payload. En single mode, si la resolución de tenant no distinguiera
        // a las empresas, la segunda petición devolvería un falso 409.
        $this->postVenta('acme.pos.localhost', $payloadBase)->assertStatus(202);
        $this->postVenta('beta.pos.localhost', [...$payloadBase, 'monto_total' => '9.50'])
            ->assertStatus(202)
            ->assertJsonPath('estado', 'RECIBIDA');
    }

    public function test_misma_clave_y_payload_en_el_mismo_tenant_sigue_siendo_idempotente(): void
    {
        $this->createTenants();

        $payload = [
            'clave_reintento' => 'CLAVE-ACME',
            'referencia' => 'P-0001',
            'fecha_emision' => '2026-08-15T12:00:00-06:00',
            'monto_total' => '4.00',
            'metodo_pago' => 'EFECTIVO',
        ];

        $primera = $this->postVenta('acme.pos.localhost', $payload);
        $segunda = $this->postVenta('acme.pos.localhost', $payload);

        $primera->assertStatus(202);
        $segunda->assertStatus(202);
        $this->assertSame($primera->json('fiscal_sale_id'), $segunda->json('fiscal_sale_id'));
    }
}
