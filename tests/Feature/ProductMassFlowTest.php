<?php

namespace Tests\Feature;

use App\Application\Printing\RenderKitchenComanda;
use App\Enums\MasaPupusa;
use App\Enums\TipoPedido;
use App\Models\Categoria;
use App\Models\Combo;
use App\Models\Establecimiento;
use App\Models\OpcionCombo;
use App\Models\Producto;
use App\Models\SesionCaja;
use App\Models\User;
use App\Services\PedidoService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductMassFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Producto $pupusa;

    private Combo $combo;

    private OpcionCombo $comboOption;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesPermissionsSeeder::class);

        $this->cashier = User::factory()->create([
            'usuario' => '44',
            'password' => '1234',
        ]);
        $this->cashier->assignRole('cajero');

        $establishment = Establecimiento::create([
            'nombre' => 'Pupusería de Prueba',
            'direccion' => 'Dirección de prueba',
        ]);

        $category = Categoria::create(['nombre' => 'Pupusas']);
        $this->pupusa = Producto::create([
            'categoria_id' => $category->getKey(),
            'nombre' => 'Pupusa revuelta',
            'precio' => 1.75,
            'disponibilidad' => 'DISPONIBLE',
            'requiere_masa' => true,
        ]);

        $this->combo = Combo::create([
            'nombre' => 'Combo de prueba',
            'precio_fijo' => 10,
            'disponibilidad' => 'DISPONIBLE',
        ]);

        $this->comboOption = OpcionCombo::create([
            'combo_id' => $this->combo->getKey(),
            'nombre' => 'Pupusas',
            'cantidad_requerida' => 2,
            'es_obligatorio' => true,
        ]);
        $this->comboOption->productos()->attach($this->pupusa->getKey());

        $this->cashier->establecimientos()->attach($establishment->getKey());

        SesionCaja::create([
            'establecimiento_id' => $establishment->getKey(),
            'usuario_apertura_id' => $this->cashier->getKey(),
            'monto_inicial' => 0,
            'fecha_apertura' => now(),
        ]);
    }

    public function test_individual_product_lines_are_split_by_mass_and_merge_when_equal(): void
    {
        $pedido = app(PedidoService::class)->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier);
        $service = app(PedidoService::class);

        $service->addProduct($pedido, $this->pupusa, $this->cashier, MasaPupusa::MAIZ->value);
        $service->addProduct($pedido, $this->pupusa, $this->cashier, MasaPupusa::MAIZ->value);
        $service->addProduct($pedido, $this->pupusa, $this->cashier, MasaPupusa::ARROZ->value);

        $details = $pedido->detalles()->orderBy('id')->get();

        $this->assertCount(2, $details);
        $this->assertSame(2, $details[0]->cantidad);
        $this->assertSame('MAIZ', data_get($details[0]->configuracion_producto, 'masa.codigo'));
        $this->assertSame(1, $details[1]->cantidad);
        $this->assertSame('ARROZ', data_get($details[1]->configuracion_producto, 'masa.codigo'));
    }

    public function test_mass_is_required_for_products_configured_with_mass(): void
    {
        $this->expectException(ValidationException::class);

        $pedido = app(PedidoService::class)->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier);

        app(PedidoService::class)->addProduct($pedido, $this->pupusa, $this->cashier);
    }

    public function test_combo_lines_are_split_by_mass_and_merge_when_equal(): void
    {
        $pedido = app(PedidoService::class)->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier);
        $service = app(PedidoService::class);
        $selection = [
            (string) $this->comboOption->getKey() => [
                (string) $this->pupusa->getKey() => 2,
            ],
        ];

        $service->addCombo($pedido, $this->combo, $selection, $this->cashier, MasaPupusa::MAIZ->value);
        $service->addCombo($pedido, $this->combo, $selection, $this->cashier, MasaPupusa::MAIZ->value);
        $service->addCombo($pedido, $this->combo, $selection, $this->cashier, MasaPupusa::ARROZ->value);

        $details = $pedido->detalles()->orderBy('id')->get();

        $this->assertCount(2, $details);
        $this->assertSame(2, $details[0]->cantidad);
        $this->assertSame('MAIZ', data_get($details[0]->configuracion_producto, 'masa.codigo'));
        $this->assertSame(1, $details[1]->cantidad);
        $this->assertSame('ARROZ', data_get($details[1]->configuracion_producto, 'masa.codigo'));
    }

    public function test_combo_allows_a_different_mass_for_each_selected_pupusa(): void
    {
        $pedido = app(PedidoService::class)->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier);
        $selection = [
            (string) $this->comboOption->getKey() => [
                (string) $this->pupusa->getKey() => [
                    MasaPupusa::MAIZ->value => 1,
                    MasaPupusa::ARROZ->value => 1,
                ],
            ],
        ];

        $detail = app(PedidoService::class)->addCombo($pedido, $this->combo, $selection, $this->cashier);

        $this->assertNull($detail->configuracion_producto);
        $items = collect($detail->seleccion_combo[0]['items']);
        $this->assertCount(2, $items);
        $this->assertSame('MAIZ', data_get($items->firstWhere('masa.codigo', 'MAIZ'), 'masa.codigo'));
        $this->assertSame('ARROZ', data_get($items->firstWhere('masa.codigo', 'ARROZ'), 'masa.codigo'));
    }
    public function test_kitchen_comanda_prints_mass_for_product_and_combo(): void
    {
        $pedido = app(PedidoService::class)->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier);
        $service = app(PedidoService::class);
        $selection = [
            (string) $this->comboOption->getKey() => [
                (string) $this->pupusa->getKey() => 2,
            ],
        ];

        $service->addProduct($pedido, $this->pupusa, $this->cashier, MasaPupusa::MAIZ->value);
        $service->addCombo($pedido, $this->combo, $selection, $this->cashier, MasaPupusa::ARROZ->value);

        $content = app(RenderKitchenComanda::class)->render($pedido->fresh());

        $this->assertStringContainsString('Masa: Maíz', $content);
        $this->assertStringContainsString('2 Pupusa revuelta · Arroz', $content);
        $this->assertStringContainsString('Combo de prueba', $content);
    }
}