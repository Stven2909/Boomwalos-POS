<?php

namespace Tests\Feature;

use App\Enums\MetodoPago;
use App\Enums\TipoPedido;
use App\Filament\Pages\Pos\ChargeOrder;
use App\Models\Categoria;
use App\Models\Establecimiento;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\SesionCaja;
use App\Models\User;
use App\Services\CobroService;
use App\Services\PedidoService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CardPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Establecimiento $establishment;

    private Producto $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesPermissionsSeeder::class);

        $this->cashier = User::factory()->create([
            'usuario' => '41',
            'password' => '1234',
        ]);
        $this->cashier->assignRole('cajero');

        $this->establishment = Establecimiento::create([
            'nombre' => 'Pupusería Demo',
            'direccion' => 'Dirección de prueba',
        ]);

        $category = Categoria::create(['nombre' => 'Bebidas']);
        $this->product = Producto::create([
            'categoria_id' => $category->getKey(),
            'nombre' => 'Horchata',
            'precio' => 1.5,
            'disponibilidad' => 'DISPONIBLE',
        ]);

        SesionCaja::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'usuario_apertura_id' => $this->cashier->getKey(),
            'monto_inicial' => 0,
            'fecha_apertura' => now(),
        ]);
    }

    public function test_card_payment_requires_approval(): void
    {
        $pedido = $this->newOrder();

        try {
            app(CobroService::class)->chargeAndSend($pedido, MetodoPago::TARJETA, null, $this->cashier);
            $this->fail('El cobro con tarjeta sin aprobación debía ser rechazado.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('aprobación del datáfono', collect($exception->errors())->flatten()->first());
        }

        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_approved_card_payment_auto_generates_referencia(): void
    {
        $pedido = $this->newOrder();

        [$pago] = app(CobroService::class)->chargeAndSend(
            $pedido,
            MetodoPago::TARJETA,
            null,
            $this->cashier,
            ['aprobada' => true],
        );

        $this->assertStringStartsWith('REF-', $pago->referencia_externa);
        $this->assertSame('CERRADO', $pedido->fresh()->estado_comercial->value);
    }

    public function test_charge_page_builds_cash_amount_with_numpad(): void
    {
        $pedido = $this->newOrder();

        $page = app(ChargeOrder::class);
        $page->pedido = $pedido;
        $page->pedido->load('detalles');

        foreach (['1', '2', '.', '5', '0', '.', '4'] as $digito) {
            $page->ingresarDigito($digito);
        }

        $this->assertSame('12.50', $page->montoRecibido);

        $page->borrarDigito();
        $this->assertSame('12.5', $page->montoRecibido);

        $page->limpiarMonto();
        $this->assertSame('', $page->montoRecibido);

        $page->ingresarDigito('5');
        $page->usarMontoExacto();
        $this->assertSame('1.50', $page->montoRecibido);
    }

    public function test_charge_page_card_button_needs_approval(): void
    {
        $pedido = $this->newOrder();

        $page = app(ChargeOrder::class);
        $page->pedido = $pedido;
        $page->pedido->load('detalles');
        $page->metodoPago = MetodoPago::TARJETA->value;
        $page->tarjetaAprobada = false;

        $this->assertFalse($page->canSubmitPayment);

        $page->tarjetaAprobada = true;
        unset($page->canSubmitPayment);
        $this->assertTrue($page->canSubmitPayment);
    }

    private function newOrder(): Pedido
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier);
        $service->addProduct($pedido, $this->product, $this->cashier);

        return $pedido;
    }
}
