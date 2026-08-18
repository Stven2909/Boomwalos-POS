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
            'nombre' => 'Pupusería Demo',
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

    public function test_charge_creates_comanda_and_ticket_jobs_with_printers(): void
    {
        Impresora::create([
            'nombre' => 'Ticket',
            'tipo' => TipoImpresora::TICKET,
            'configuracion' => ['driver' => 'queue'],
        ]);

        $pago = app(CobroService::class)->charge(
            $this->newOrder(),
            MetodoPago::TARJETA,
            null,
            $this->cashier,
            ['aprobada' => true, 'referencia' => 'TEST-REF'],
        );

        $this->assertDatabaseHas('trabajo_impresion', [
            'pedido_id' => $pago->pedido_id,
            'tipo_trabajo' => 'COMANDA',
            'estado' => 'PENDIENTE',
        ]);
        $this->assertDatabaseHas('trabajo_impresion', [
            'pedido_id' => $pago->pedido_id,
            'tipo_trabajo' => 'TICKET',
            'estado' => 'PENDIENTE',
        ]);
        $this->assertDatabaseHas('evento_auditorias', [
            'entidad_id' => $pago->pedido_id,
            'tipo_evento' => 'ticket_en_cola',
        ]);
    }

    public function test_charge_without_ticket_printer_creates_error_job(): void
    {
        $pago = app(CobroService::class)->charge(
            $this->newOrder(),
            MetodoPago::TARJETA,
            null,
            $this->cashier,
            ['aprobada' => true, 'referencia' => 'TEST-REF'],
        );

        $this->assertDatabaseCount('pagos', 1);
        $this->assertDatabaseHas('trabajo_impresion', [
            'pedido_id' => $pago->pedido_id,
            'tipo_trabajo' => 'COMANDA',
            'estado' => 'PENDIENTE',
        ]);
        $this->assertDatabaseHas('trabajo_impresion', [
            'pedido_id' => $pago->pedido_id,
            'tipo_trabajo' => 'TICKET',
            'estado' => 'ERROR',
        ]);
    }

    public function test_lista_pedidos_reprint_action_queues_a_reprint(): void
    {
        Impresora::create([
            'nombre' => 'Ticket',
            'tipo' => TipoImpresora::TICKET,
            'configuracion' => ['driver' => 'queue'],
        ]);

        $pedido = $this->newOrder();
        app(CobroService::class)->charge($pedido, MetodoPago::TARJETA, null, $this->cashier, ['aprobada' => true, 'referencia' => 'TEST-REF']);

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
