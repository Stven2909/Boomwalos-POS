<?php

namespace Tests\Feature\Fiscal;

use App\Application\Fiscal\HmacSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class FiscalMockTest extends TestCase
{
    use RefreshDatabase;

    private function postVenta(array $payload, array $opciones = []): TestResponse
    {
        $secret = $opciones['secret'] ?? (string) config('fiscal.mock.secret');
        $timestamp = $opciones['timestamp'] ?? time();
        $content = json_encode($payload);

        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_FISCAL_KEY' => $opciones['key'] ?? 'est-test',
            'HTTP_X_FISCAL_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_FISCAL_HMAC' => $opciones['firma'] ?? HmacSigner::sign($content, $secret),
        ];

        return $this->call('POST', '/api/fiscal/v1/ventas', [], [], [], $server, $content);
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'clave_reintento' => 'v-2026-08-15-0001',
            'referencia' => 'P-000123',
            'fecha_emision' => '2026-08-15T12:00:00-06:00',
            'monto_total' => '4.00',
            'metodo_pago' => 'EFECTIVO',
        ], $extra);
    }

    public function test_ventas_endpoint_rejects_when_mock_disabled(): void
    {
        config()->set('fiscal.mock.enabled', false);

        $this->postVenta($this->payload())
            ->assertNotFound();
    }

    public function test_mock_is_never_available_in_production(): void
    {
        config()->set('app.env', 'production');

        $this->postVenta($this->payload())
            ->assertNotFound();
    }

    public function test_ventas_endpoint_rejects_missing_signature(): void
    {
        $this->postVenta($this->payload(), ['firma' => ''])
            ->assertStatus(401)
            ->assertJsonPath('message', 'FIRMA_REQUERIDA');
    }

    public function test_ventas_endpoint_rejects_stale_timestamp(): void
    {
        $this->postVenta($this->payload(), ['timestamp' => time() - 3600])
            ->assertStatus(401)
            ->assertJsonPath('message', 'MARCA_TIEMPO_VENCIDA');
    }

    public function test_ventas_endpoint_rejects_invalid_signature(): void
    {
        $this->postVenta($this->payload(), ['secret' => 'secreto-incorrecto'])
            ->assertStatus(401)
            ->assertJsonPath('message', 'FIRMA_INVALIDA');
    }

    public function test_ventas_endpoint_accepts_a_valid_request(): void
    {
        $this->postVenta($this->payload())
            ->assertStatus(202)
            ->assertJsonPath('estado', 'RECIBIDA')
            ->assertJsonPath('qr_url', null)
            ->assertJsonStructure(['fiscal_sale_id']);
    }

    public function test_ventas_endpoint_is_idempotent_for_same_clave_and_payload(): void
    {
        $primera = $this->postVenta($this->payload());
        $segunda = $this->postVenta($this->payload());

        $primera->assertStatus(202);
        $segunda->assertStatus(202);
        $this->assertSame(
            $primera->json('fiscal_sale_id'),
            $segunda->json('fiscal_sale_id'),
        );
    }

    public function test_ventas_endpoint_returns_409_for_same_clave_different_payload(): void
    {
        $this->postVenta($this->payload())->assertStatus(202);

        $this->postVenta($this->payload(['monto_total' => '9.50']))
            ->assertStatus(409)
            ->assertJsonPath('error', 'CLAVE_REUTILIZADA');
    }

    public function test_ventas_endpoint_validates_the_body(): void
    {
        $this->postVenta($this->payload(['monto_total' => 'no-es-numero']))
            ->assertStatus(422);
    }
}
