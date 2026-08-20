<?php

namespace Tests\Feature\Fiscal;

use App\Models\ConfiguracionFiscal;
use App\Models\Establecimiento;
use App\Services\Fiscal\FiscalOnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FiscalOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private Establecimiento $establecimiento;
    private FiscalOnboardingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'fiscal.provisioning_token' => 'test-provisioning-token',
            'fiscal.onboarding_url' => 'https://api-fiscal.test/api/v1/onboarding/emisor',
            'fiscal.mock.enabled' => false,
        ]);

        $this->establecimiento = Establecimiento::create([
            'nombre' => 'Pupusería Boomwalos Matriz',
            'direccion' => 'Calle Los Próceres #456, San Salvador',
        ]);

        $this->service = app(FiscalOnboardingService::class);
    }

    public function test_successful_onboarding_provisions_emitter_and_saves_credentials(): void
    {
        Http::fake([
            'https://api-fiscal.test/api/v1/onboarding/emisor' => Http::response([
                'success' => true,
                'message' => 'Emisor aprovisionado exitosamente.',
                'client_id' => 'pos-client-test-8899',
                'secret' => 'super-secret-key-12345',
            ], 201),
        ]);

        $resultado = $this->service->provisionar([
            'establecimiento_id' => $this->establecimiento->getKey(),
            'ambiente' => '00',
            'nit' => '0614-010190-101-1',
            'nrc' => '123456-7',
            'razon_social' => 'PUPUSERIA BOOMWALOS S.A. DE C.V.',
            'giro' => 'Venta de pupusas y comida típica',
            'codigo_establecimiento' => '0001',
            'codigo_punto_venta' => '001',
            'p12_base64' => base64_encode('fake-p12-certificate-content'),
            'password' => 'CertPass1234!',
        ]);

        $this->assertTrue($resultado['success']);
        $this->assertEquals('pos-client-test-8899', $resultado['client_id']);
        $this->assertEquals('00', $resultado['ambiente']);

        $this->assertDatabaseHas('configuraciones_fiscales', [
            'establecimiento_id' => $this->establecimiento->getKey(),
            'razon_social' => 'PUPUSERIA BOOMWALOS S.A. DE C.V.',
            'nit' => '0614-010190-101-1',
            'nrc' => '123456-7',
            'ambiente' => '00',
            'giro' => 'Venta de pupusas y comida típica',
            'codigo_establecimiento' => '0001',
            'codigo_punto_venta' => '001',
            'cliente_key' => 'pos-client-test-8899',
            'fiscal_habilitada' => true,
        ]);

        $config = ConfiguracionFiscal::where('establecimiento_id', $this->establecimiento->getKey())->firstOrFail();
        $this->assertEquals('super-secret-key-12345', $config->cliente_secret);
    }

    public function test_onboarding_in_production_environment_01(): void
    {
        Http::fake([
            'https://api-fiscal.test/api/v1/onboarding/emisor' => Http::response([
                'success' => true,
                'client_id' => 'pos-prod-999',
                'secret' => 'prod-secret-999',
            ], 201),
        ]);

        $resultado = $this->service->provisionar([
            'establecimiento_id' => $this->establecimiento->getKey(),
            'ambiente' => '01',
            'nit' => '0614-010190-101-1',
            'razon_social' => 'BOOMWALOS EN VIVO S.A.',
            'p12_base64' => base64_encode('prod-cert'),
            'password' => 'prodPass99!',
        ]);

        $this->assertTrue($resultado['success']);
        $this->assertEquals('01', $resultado['ambiente']);

        $this->assertDatabaseHas('configuraciones_fiscales', [
            'establecimiento_id' => $this->establecimiento->getKey(),
            'ambiente' => '01',
            'cliente_key' => 'pos-prod-999',
        ]);
    }

    public function test_onboarding_fails_when_api_returns_error(): void
    {
        Http::fake([
            'https://api-fiscal.test/api/v1/onboarding/emisor' => Http::response([
                'success' => false,
                'message' => 'Contraseña del certificado .p12 incorrecta.',
            ], 422),
        ]);

        $resultado = $this->service->provisionar([
            'establecimiento_id' => $this->establecimiento->getKey(),
            'nit' => '0614-010190-101-1',
            'razon_social' => 'EMPRESA TEST',
            'p12_base64' => base64_encode('invalid-cert'),
            'password' => 'wrong-pass',
        ]);

        $this->assertFalse($resultado['success']);
        $this->assertStringContainsString('Contraseña del certificado .p12 incorrecta', $resultado['message']);
        $this->assertEquals(422, $resultado['status']);
    }

    public function test_onboarding_fails_when_provisioning_token_missing(): void
    {
        config(['fiscal.provisioning_token' => null]);

        $resultado = $this->service->provisionar([
            'establecimiento_id' => $this->establecimiento->getKey(),
            'nit' => '0614-010190-101-1',
            'razon_social' => 'EMPRESA TEST',
            'p12_base64' => base64_encode('some-cert'),
            'password' => 'password',
        ]);

        $this->assertFalse($resultado['success']);
        $this->assertStringContainsString('PROVISIONING_TOKEN', $resultado['message']);
    }

    public function test_onboarding_fails_when_required_fields_missing(): void
    {
        $resultado = $this->service->provisionar([
            'establecimiento_id' => $this->establecimiento->getKey(),
            'nit' => '',
            'razon_social' => '',
            'p12_base64' => '',
            'password' => '',
        ]);

        $this->assertFalse($resultado['success']);
        $this->assertStringContainsString('obligatorios', $resultado['message']);
    }
}
