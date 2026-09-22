<?php

namespace Tests\Feature\Fiscal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class FiscalWebhookBodyTest extends TestCase
{
    use RefreshDatabase;

    private function postWebhook(array $payload, array $headers = [], string $contentType = 'application/json'): TestResponse
    {
        $content = json_encode($payload);
        $path = '/api/fiscal/v1/webhooks';

        $server = ['CONTENT_TYPE' => $contentType];
        foreach ($headers as $key => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $key))] = $value;
        }

        return $this->call('POST', $path, [], [], [], $server, $content);
    }

    public function test_webhook_reads_json_body_even_with_wrong_content_type(): void
    {
        $response = $this->postWebhook(
            ['codigoGeneracion' => 'NO-VINCULADO-001', 'status' => 'PROCESSED'],
            ['X-POsFact-Event' => 'DTE_PROCESSED'],
            'text/plain',
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Evento recibido pero documento no vinculado');
    }

    public function test_webhook_reads_json_body_with_correct_content_type(): void
    {
        $response = $this->postWebhook(
            ['codigoGeneracion' => 'NO-VINCULADO-002', 'status' => 'SEALED'],
            ['X-POsFact-Event' => 'DTE_SEALED'],
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Evento recibido pero documento no vinculado');
    }

    public function test_webhook_reports_missing_codigo_when_body_is_empty(): void
    {
        $response = $this->postWebhook(
            [],
            ['X-POsFact-Event' => 'DTE_PROCESSED'],
        );

        $response->assertStatus(200)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'codigoGeneracion no proporcionado');
    }
}
