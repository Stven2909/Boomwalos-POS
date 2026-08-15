<?php

namespace Tests\Feature;

use App\Enums\MetodoPago;
use App\Enums\TipoImpresora;
use App\Enums\TipoPedido;
use App\Filament\Pages\Pos\ListaPedidos;
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

class ChargeTicketFlowTest extends TestCase
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
            'usuario' => '32',
            'password' => '1234',
            'nombre' => 'Carlos Rivera',
        ]);
        $this->cashier->assignRole('cajero');

        $this->establishment = Establecimiento::create([
            'nombre' => 'Los Boomwalos',
            'direccion' => 'Dirección de prueba',
        ]);

        $category = Categoria::create(['nombre' => 'Bebidas']);
        $this->product = Producto::create([
            'categoria_id' => $category->getKey(),
            'nombre' => 'Jugo de naranja',
            'precio' => 3,
            'disponibilidad' => 'DISPONIBLE',
        ]);

        SesionCaja::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'usuario_apertura_id' => $this->cashier->getKey(),
            'monto_inicial' => 0,
            'fecha_apertura' => now(),
        ]);

        Impresora::create([
            'nombre' => 'Cocina',
            'tipo' => TipoImpresora::COMANDA,
            'configuracion' => ['driver' => 'queue'],
        ]);
    }

    public function test_charge_and_send_creates_ticket_and_comanda_inside_transaction(): void
    {
        Impresora::create([
            'nombre' => 'Ticket',
            'tipo' => TipoImpresora::TICKET,
            'configuracion' => ['driver' => 'queue'],
        ]);

        [$payment, $tanda, $ticketResult] = app(CobroService::class)->chargeAndSend(
            $this->newOrder(),
            MetodoPago::TARJETA,
            null,
            $this->cashier,
            ['aprobada' => true, 'referencia' => 'TEST-REF'],
        );

        $this->assertNotNull($ticketResult->trabajo);
        $this->assertSame('TICKET', $ticketResult->trabajo->tipo_trabajo->value);
        $this->assertDatabaseHas('trabajo_impresion', [
            'id' => $ticketResult->trabajo->getKey(),
            'tipo_trabajo' => 'TICKET',
            'es_reimpresion' => 0,
        ]);
        $this->assertDatabaseHas('tandas_pedido', [
            'id' => $tanda->getKey(),
        ]);
        $this->assertDatabaseHas('evento_auditorias', [
            'entidad_id' => $payment->pedido_id,
            'tipo_evento' => 'ticket_en_cola',
        ]);
    }

    public function test_charge_without_ticket_printer_succeeds_and_audits(): void
    {
        [$payment] = app(CobroService::class)->chargeAndSend(
            $this->newOrder(),
            MetodoPago::TARJETA,
            null,
            $this->cashier,
            ['aprobada' => true, 'referencia' => 'TEST-REF'],
        );

        $this->assertDatabaseCount('pagos', 1);
        $this->assertDatabaseHas('evento_auditorias', [
            'entidad_id' => $payment->pedido_id,
            'tipo_evento' => 'ticket_sin_impresora',
        ]);
        $this->assertDatabaseMissing('trabajo_impresion', ['tipo_trabajo' => 'TICKET']);
    }

    public function test_lista_pedidos_reprint_action_queues_a_reprint(): void
    {
        Impresora::create([
            'nombre' => 'Ticket',
            'tipo' => TipoImpresora::TICKET,
            'configuracion' => ['driver' => 'queue'],
        ]);

        $pedido = $this->newOrder();
        app(CobroService::class)->chargeAndSend($pedido, MetodoPago::TARJETA, null, $this->cashier, ['aprobada' => true, 'referencia' => 'TEST-REF']);

        $this->actingAs($this->cashier);

        $page = app(ListaPedidos::class);
        $page->reimprimirTicket($pedido->getKey());

        $this->assertSame('Ticket reimpreso y encolado para imprimir.', $page->feedback);
        $this->assertDatabaseHas('trabajo_impresion', [
            'pedido_id' => $pedido->getKey(),
            'tipo_trabajo' => 'TICKET',
            'es_reimpresion' => 1,
        ]);
    }

    private function newOrder(): Pedido
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier);
        $service->addProduct($pedido, $this->product, $this->cashier);

        return $pedido;
    }
}
