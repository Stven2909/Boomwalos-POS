<?php

namespace Tests\Feature;

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
            'nombre' => 'Pupusería Demo',
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
            'nombre' => 'Cocina',
            'tipo' => TipoImpresora::COMANDA,
            'activa' => true,
            'configuracion' => ['driver' => 'queue'],
        ]);

        Impresora::create([
            'nombre' => 'Ticket',
            'tipo' => TipoImpresora::TICKET,
            'activa' => true,
            'configuracion' => ['driver' => 'queue'],
        ]);
    }

    public function test_charge_creates_ticket_job_with_pending_status(): void
    {
        $pedido = $this->chargedOrder();

        $ticket = $pedido->trabajosImpresion()
            ->where('tipo_trabajo', 'TICKET')
            ->first();

        $this->assertNotNull($ticket);
        $this->assertFalse((bool) $ticket->es_reimpresion);
        $this->assertNotNull($ticket->original_uid);
        $this->assertSame('PENDIENTE', $ticket->estado->value);
    }

    public function test_charge_creates_comanda_job_alongside_ticket(): void
    {
        $pedido = $this->chargedOrder();

        $comanda = $pedido->trabajosImpresion()
            ->where('tipo_trabajo', 'COMANDA')
            ->first();

        $this->assertNotNull($comanda);
        $this->assertSame('PENDIENTE', $comanda->estado->value);
    }

    public function test_ticket_content_includes_order_lines_totals_and_cashier(): void
    {
        $pedido = $this->chargedOrder(MetodoPago::EFECTIVO, '10.00');

        $contenido = $pedido->trabajosImpresion()
            ->where('tipo_trabajo', 'TICKET')
            ->value('contenido');

        $this->assertStringContainsString('PUPUSERÍA DEMO', $contenido);
        $this->assertStringContainsString('TICKET DE CLIENTE', $contenido);
        $this->assertStringContainsString('PARA LLEVAR · MOSTRADOR', $contenido);
        $this->assertStringContainsString('1 x Limonada fresca', $contenido);
        $this->assertStringContainsString('TOTAL  $4.00', $contenido);
        $this->assertStringContainsString('PAGO   Efectivo', $contenido);
        $this->assertStringContainsString('RECIBIDO $10.00', $contenido);
        $this->assertStringContainsString('CAMBIO  $6.00', $contenido);
        $this->assertStringContainsString('Lucía García', $contenido);
    }

    public function test_charge_without_printers_creates_error_jobs(): void
    {
        Impresora::query()->delete();

        $pedido = $this->chargedOrder();

        $this->assertDatabaseCount('trabajo_impresion', 2);
        $this->assertDatabaseHas('trabajo_impresion', [
            'pedido_id' => $pedido->getKey(),
            'tipo_trabajo' => 'COMANDA',
            'estado' => 'ERROR',
        ]);
        $this->assertDatabaseHas('trabajo_impresion', [
            'pedido_id' => $pedido->getKey(),
            'tipo_trabajo' => 'TICKET',
            'estado' => 'ERROR',
        ]);
    }

    public function test_reprint_creates_a_reimpresion_job_linked_to_the_original(): void
    {
        $pedido = $this->chargedOrder();

        $original = $pedido->trabajosImpresion()
            ->where('tipo_trabajo', 'TICKET')
            ->where('es_reimpresion', false)
            ->firstOrFail();

        $result = app(ReprintTicket::class)->handle($pedido, $this->cashier, 'Cliente lo perdió');

        $this->assertTrue((bool) $result->trabajo->es_reimpresion);
        $this->assertSame($original->getKey(), $result->trabajo->reimpresion_de_id);
        $this->assertNotNull($result->trabajo->original_uid);
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

        $this->assertNull($result->trabajo);
    }

    private function chargedOrder(MetodoPago $metodo = MetodoPago::TARJETA, ?string $monto = null): Pedido
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier);
        $service->addProduct($pedido, $this->product, $this->cashier);
        $tarjeta = $metodo === MetodoPago::TARJETA ? ['aprobada' => true, 'referencia' => 'TEST-REF'] : null;
        app(CobroService::class)->charge($pedido, $metodo, $monto, $this->cashier, $tarjeta);

        return $pedido->fresh(['pago']);
    }
}
