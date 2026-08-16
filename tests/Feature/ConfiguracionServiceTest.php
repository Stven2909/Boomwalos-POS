<?php

namespace Tests\Feature;

use App\Models\Establecimiento;
use App\Services\ConfiguracionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfiguracionServiceTest extends TestCase
{
    use RefreshDatabase;

    private Establecimiento $establishment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->establishment = Establecimiento::create([
            'nombre' => 'Pupusería Demo',
            'direccion' => 'Dirección de prueba',
        ]);
    }

    public function test_get_returns_default_when_not_configured(): void
    {
        $this->assertSame('$', app(ConfiguracionService::class)->get('moneda.simbolo', '$'));
        $this->assertSame([1, 5, 10], app(ConfiguracionService::class)->get('pos.montos_rapidos_efectivo', [1, 5, 10]));
    }

    public function test_set_and_get_persists_typed_value(): void
    {
        $service = app(ConfiguracionService::class);

        $service->set('pos.montos_rapidos_efectivo', [1, 5, 10, 20, 50]);
        $service->set('moneda.simbolo', '$');
        $service->set('impresion.ticket_activo', true);

        $this->assertSame([1, 5, 10, 20, 50], $service->get('pos.montos_rapidos_efectivo'));
        $this->assertSame('$', $service->get('moneda.simbolo'));
        $this->assertTrue($service->get('impresion.ticket_activo'));

        $this->assertDatabaseHas('configuraciones', [
            'establecimiento_id' => $this->establishment->getKey(),
            'clave' => 'moneda.simbolo',
        ]);
    }

    public function test_set_rejects_wrong_type_for_registered_clave(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(ConfiguracionService::class)->set('moneda.simbolo', 123);
    }

    public function test_unknown_clave_accepts_any_json_value(): void
    {
        $service = app(ConfiguracionService::class);

        $service->set('fiscal.nit', '0614-120689-101-7');

        $this->assertSame('0614-120689-101-7', $service->get('fiscal.nit'));
    }

    public function test_set_overwrites_the_previous_value(): void
    {
        $service = app(ConfiguracionService::class);

        $service->set('pos.montos_rapidos_efectivo', [1, 2, 3]);
        $service->set('pos.montos_rapidos_efectivo', [5, 10]);

        $this->assertSame([5, 10], $service->get('pos.montos_rapidos_efectivo'));
        $this->assertSame(1, \App\Models\Configuracion::query()->count());
    }
}
