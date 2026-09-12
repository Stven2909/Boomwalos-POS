<?php

namespace Tests\Feature;

use App\Contracts\EstablishmentContextInterface;
use App\Enums\EstadoComercialPedido;
use App\Enums\EstadoMesa;
use App\Enums\FlujoPos;
use App\Enums\MetodoPago;
use App\Enums\TipoPedido;
use App\Filament\Pages\IT\PosOperationSettings;
use App\Models\Categoria;
use App\Models\Establecimiento;
use App\Models\Mesa;
use App\Models\Producto;
use App\Models\SesionCaja;
use App\Models\TrabajoImpresion;
use App\Models\User;
use App\Services\CobroService;
use App\Services\ConfiguracionFlujosPosService;
use App\Services\PedidoService;
use App\Services\PoliticaFlujosPos;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PosFlowConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

    private Establecimiento $establishment;

    private Producto $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesPermissionsSeeder::class);

        $this->admin = User::factory()->create(['usuario' => '10', 'password' => '1234']);
        $this->admin->assignRole('administrador');
        $this->cashier = User::factory()->create(['usuario' => '11', 'password' => '1234']);
        $this->cashier->assignRole('cajero');

        $this->establishment = Establecimiento::create([
            'nombre' => 'Sucursal Centro',
            'direccion' => 'San Salvador',
        ]);
        $this->cashier->establecimientos()->attach($this->establishment);

        $category = Categoria::create(['nombre' => 'Bebidas']);
        $this->product = Producto::create([
            'categoria_id' => $category->getKey(),
            'nombre' => 'Horchata de morro',
            'precio' => 2.50,
            'disponibilidad' => 'DISPONIBLE',
        ]);

        SesionCaja::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'usuario_apertura_id' => $this->cashier->getKey(),
            'monto_inicial' => 0,
            'fecha_apertura' => now(),
        ]);
    }

    public function test_only_dedicated_permission_can_change_pos_flows(): void
    {
        $this->actingAs($this->cashier);

        $this->assertFalse(PosOperationSettings::canAccess());
        $this->expectException(AuthorizationException::class);

        app(ConfiguracionFlujosPosService::class)->actualizar($this->counterOnly(), $this->cashier);
    }

    public function test_configuration_is_saved_for_active_branch_and_audited(): void
    {
        $this->actingAs($this->admin);

        $settings = app(ConfiguracionFlujosPosService::class)->actualizar($this->counterOnly(), $this->admin);

        $this->assertTrue($settings->mostradorPrepago);
        $this->assertFalse($settings->mesaPostpago);
        $this->assertDatabaseHas('configuraciones', [
            'establecimiento_id' => $this->establishment->getKey(),
            'clave' => PoliticaFlujosPos::CONFIG_KEY,
        ]);
        $this->assertDatabaseHas('evento_auditorias', [
            'entidad_tipo' => Establecimiento::class,
            'entidad_id' => $this->establishment->getKey(),
            'tipo_evento' => 'configuracion_flujos_pos_actualizada',
        ]);
    }

    public function test_configuration_is_isolated_between_branches(): void
    {
        $this->actingAs($this->admin);
        $second = Establecimiento::create([
            'nombre' => 'Sucursal Norte',
            'direccion' => 'Mejicanos',
        ]);
        $context = app(EstablishmentContextInterface::class);
        $context->set($this->establishment->getKey());

        app(ConfiguracionFlujosPosService::class)->actualizar($this->counterOnly(), $this->admin);

        $context->reset();
        $context->set($second->getKey());
        $secondSettings = app(PoliticaFlujosPos::class)->actual(true);

        $this->assertTrue($secondSettings->mostradorPrepago);
        $this->assertTrue($secondSettings->mesaPostpago);
        $this->assertDatabaseMissing('configuraciones', [
            'establecimiento_id' => $second->getKey(),
            'clave' => PoliticaFlujosPos::CONFIG_KEY,
        ]);
    }

    public function test_it_page_is_visible_only_with_dedicated_permission(): void
    {
        $this->actingAs($this->admin)
            ->get(PosOperationSettings::getUrl())
            ->assertSuccessful()
            ->assertSee('Operación del POS')
            ->assertSee('Sucursal Centro');

        $this->actingAs($this->cashier)
            ->get(PosOperationSettings::getUrl())
            ->assertRedirect();

        $this->assertFalse(PosOperationSettings::canAccess());
    }

    public function test_disabling_flow_is_blocked_when_real_orders_exist(): void
    {
        $this->actingAs($this->admin);
        $pedido = app(PedidoService::class)->startOrder(TipoPedido::PARA_LLEVAR, $this->admin);
        app(PedidoService::class)->addProduct($pedido, $this->product, $this->admin);

        $this->expectException(ValidationException::class);

        app(ConfiguracionFlujosPosService::class)->actualizar([
            'version' => 1,
            'mostrador_prepago' => false,
            'mesa_postpago' => true,
            'predeterminado' => FlujoPos::MESA_POSTPAGO->value,
        ], $this->admin);
    }

    public function test_empty_table_draft_is_cancelled_and_does_not_block_disable(): void
    {
        $this->actingAs($this->admin);
        $mesa = Mesa::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'numero' => '1',
            'zona' => 'SALON',
            'estado' => EstadoMesa::LIBRE,
            'activa' => true,
        ]);
        $pedido = app(PedidoService::class)->startOrder(TipoPedido::MESA, $this->admin, $mesa->getKey());

        app(ConfiguracionFlujosPosService::class)->actualizar($this->counterOnly(), $this->admin);

        $this->assertSame(EstadoComercialPedido::CANCELADO, $pedido->fresh()->estado_comercial);
        $this->assertSame(EstadoMesa::LIBRE, $mesa->fresh()->estado);
    }

    public function test_disabled_flow_cannot_be_started_directly(): void
    {
        $this->actingAs($this->admin);
        app(ConfiguracionFlujosPosService::class)->actualizar($this->counterOnly(), $this->admin);

        $this->expectException(ValidationException::class);

        app(PedidoService::class)->startOrder(TipoPedido::MESA, $this->admin, 999);
    }

    public function test_table_must_send_kitchen_and_request_account_before_payment(): void
    {
        $this->actingAs($this->cashier);
        $mesa = Mesa::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'numero' => '2',
            'zona' => 'SALON',
            'estado' => EstadoMesa::LIBRE,
            'activa' => true,
        ]);
        $pedido = app(PedidoService::class)->startOrder(TipoPedido::MESA, $this->cashier, $mesa->getKey());
        app(PedidoService::class)->addProduct($pedido, $this->product, $this->cashier);

        try {
            app(CobroService::class)->chargeAndSend($pedido, MetodoPago::EFECTIVO, '3.00', $this->cashier);
            $this->fail('La mesa no debe cobrarse antes de solicitar la cuenta.');
        } catch (ValidationException) {
            $this->assertDatabaseMissing('pagos', ['pedido_id' => $pedido->getKey()]);
        }

        app(PedidoService::class)->sendPendingBatch($pedido, $this->cashier);
        app(PedidoService::class)->sendToCashRegister($pedido, $this->cashier);
        [, $comanda] = app(CobroService::class)->chargeAndSend($pedido, MetodoPago::EFECTIVO, '3.00', $this->cashier);

        $this->assertNull($comanda);
        $this->assertSame(EstadoComercialPedido::CERRADO, $pedido->fresh()->estado_comercial);
        $this->assertSame(EstadoMesa::LIBRE, $mesa->fresh()->estado);
        $this->assertSame(1, TrabajoImpresion::query()->where('pedido_id', $pedido->getKey())->where('tipo_trabajo', 'COMANDA')->count());
    }

    public function test_counter_payment_prints_only_lines_not_sent_before(): void
    {
        $this->actingAs($this->cashier);
        $second = Producto::create([
            'categoria_id' => $this->product->categoria_id,
            'nombre' => 'Café de olla',
            'precio' => 1.50,
            'disponibilidad' => 'DISPONIBLE',
        ]);
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier);
        $service->addProduct($pedido, $this->product, $this->cashier);
        $firstJob = $service->sendPendingBatch($pedido, $this->cashier);
        $service->addProduct($pedido, $second, $this->cashier);

        [, $secondJob] = app(CobroService::class)->chargeAndSend($pedido, MetodoPago::EFECTIVO, '5.00', $this->cashier);

        $this->assertNotNull($secondJob);
        $this->assertNotSame($firstJob->getKey(), $secondJob->getKey());
        $this->assertStringContainsString('Horchata de morro', $firstJob->contenido);
        $this->assertStringNotContainsString('Café de olla', $firstJob->contenido);
        $this->assertStringContainsString('Café de olla', $secondJob->contenido);
        $this->assertStringNotContainsString('Horchata de morro', $secondJob->contenido);
    }

    private function counterOnly(): array
    {
        return [
            'version' => 1,
            'mostrador_prepago' => true,
            'mesa_postpago' => false,
            'predeterminado' => FlujoPos::MOSTRADOR_PREPAGO->value,
        ];
    }
}
