<?php

namespace Tests\Feature;

use App\Application\Printing\QueueCustomerTicket;
use App\Application\Printing\QueueTicketResult;
use App\Application\Printing\ReprintTicket;
use App\Enums\MetodoPago;
use App\Enums\TipoImpresora;
use App\Enums\TipoPedido;
use App\Models\Categoria;
use App\Models\Establecimiento;
use App\Models\Impresora;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\SesionCaja;
use App\Models\User;
use App\Services\CobroService;
use App\Services\PedidoService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueueCustomerTicketTest extends TestCase
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
            'usuario' => '31',
            'password' => '1234',
            'nombre' => 'Lucía García',
        ]);
        $this->cashier->assignRole('cajero');

        $this->establishment = Establecimiento::create([
            'nombre' => 'Los Boomwalos',
            'direccion' => 'Dirección de prueba',
        ]);

        $category = Categoria::create(['nombre' => 'Bebidas']);
        $this->product = Producto::create([
            'categoria_id' => $category->getKey(),
            'nombre' => 'Limonada fresca',
            'precio' => 4,
            'disponibilidad' => 'DISPONIBLE',
        ]);

        SesionCaja::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'usuario_apertura_id' => $this->cashier->getKey(),
            'monto_inicial' => 0,
            'fecha_apertura' => now(),
        ]);

        Impresora::create([
            'nombre' => 'Ticket',
            'tipo' => TipoImpresora::TICKET,
            'configuracion' => ['driver' => 'queue'],
        ]);
    }

    public function test_charge_creates_a_single_original_customer_ticket(): void
    {
        $pedido = $this->chargedOrder();

        $tickets = $pedido->trabajosImpresion()
            ->where('tipo_trabajo', 'TICKET')
            ->get();

        $this->assertCount(1, $tickets);
        $this->assertFalse((bool) $tickets->first()->es_reimpresion);
        $this->assertNotNull($tickets->first()->original_uid);
        $this->assertSame('PENDIENTE', $tickets->first()->estado->value);
    }

    public function test_ticket_content_includes_order_lines_totals_and_cashier(): void
    {
        $pedido = $this->chargedOrder(MetodoPago::EFECTIVO, '10.00');

        $contenido = $pedido->trabajosImpresion()
            ->where('tipo_trabajo', 'TICKET')
            ->value('contenido');

        $this->assertStringContainsString('LOS BOOMWALOS', $contenido);
        $this->assertStringContainsString('TICKET DE CLIENTE', $contenido);
        $this->assertStringContainsString('PARA LLEVAR · MOSTRADOR', $contenido);
        $this->assertStringContainsString('1 x Limonada fresca', $contenido);
        $this->assertStringContainsString('TOTAL  $4.00', $contenido);
        $this->assertStringContainsString('PAGO   Efectivo', $contenido);
        $this->assertStringContainsString('RECIBIDO $10.00', $contenido);
        $this->assertStringContainsString('CAMBIO  $6.00', $contenido);
        $this->assertStringContainsString('Lucía García', $contenido);
    }

    public function test_charge_without_ticket_printer_returns_no_printer_without_failing(): void
    {
        Impresora::where('tipo', TipoImpresora::TICKET->value)->delete();

        $pedido = $this->chargedOrder();

        $this->assertDatabaseCount('trabajo_impresion', 0);
        $this->assertSame('COBRADO', $pedido->estado_comercial->value);
    }

    public function test_queueing_is_idempotent_for_the_same_order(): void
    {
        $pedido = $this->chargedOrder();

        $first = app(QueueCustomerTicket::class)->handle($pedido, $pedido->pago, $this->cashier);
        $second = app(QueueCustomerTicket::class)->handle($pedido, $pedido->pago, $this->cashier);

        $this->assertSame(QueueTicketResult::CREATED, $first->status);
        $this->assertSame($first->trabajo->getKey(), $second->trabajo->getKey());
        $this->assertDatabaseCount('trabajo_impresion', 1);
        $this->assertSame(1, $pedido->trabajosImpresion()->where('tipo_trabajo', 'TICKET')->count());
    }

    public function test_reprint_creates_a_reimpresion_job_linked_to_the_original(): void
    {
        $pedido = $this->chargedOrder();

        $original = $pedido->trabajosImpresion()
            ->where('tipo_trabajo', 'TICKET')
            ->where('es_reimpresion', false)
            ->firstOrFail();

        $result = app(ReprintTicket::class)->handle($pedido, $this->cashier, 'Cliente lo perdió');

        $this->assertSame(QueueTicketResult::CREATED, $result->status);
        $this->assertTrue((bool) $result->trabajo->es_reimpresion);
        $this->assertSame($original->getKey(), $result->trabajo->reimpresion_de_id);
        $this->assertNull($result->trabajo->original_uid);
        $this->assertSame('Cliente lo perdió', $result->trabajo->motivo_reimpresion);
        $this->assertSame($this->cashier->getKey(), $result->trabajo->usuario_reimpresion_id);
        $this->assertSame($original->contenido, $result->trabajo->contenido);
        $this->assertSame(2, $pedido->trabajosImpresion()->where('tipo_trabajo', 'TICKET')->count());
    }

    public function test_reprint_without_ticket_printer_returns_no_printer(): void
    {
        Impresora::where('tipo', TipoImpresora::TICKET->value)->delete();

        $pedido = $this->chargedOrder();

        $result = app(ReprintTicket::class)->handle($pedido, $this->cashier);

        $this->assertSame(QueueTicketResult::NO_PRINTER, $result->status);
        $this->assertNull($result->trabajo);
    }

    private function chargedOrder(MetodoPago $metodo = MetodoPago::TARJETA, ?string $monto = null): Pedido
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier);
        $service->addProduct($pedido, $this->product, $this->cashier);
        $tarjeta = $metodo === MetodoPago::TARJETA ? ['aprobada' => true, 'referencia' => 'TEST-REF'] : null;
        app(CobroService::class)->chargeAndSend($pedido, $metodo, $monto, $this->cashier, $tarjeta);

        return $pedido->fresh(['pago']);
    }
}
