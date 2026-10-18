<?php

namespace Tests\Feature\Fiscal;

use App\Application\Fiscal\FiscalOutboxService;
use App\Enums\EstadoMesa;
use App\Enums\EstadoVentaFiscal;
use App\Enums\MetodoPago;
use App\Enums\TipoPedido;
use App\Enums\ZonaMesa;
use App\Models\Categoria;
use App\Models\ConfiguracionFiscal;
use App\Models\Establecimiento;
use App\Models\Impresora;
use App\Models\Mesa;
use App\Models\Producto;
use App\Models\SesionCaja;
use App\Models\TrabajoImpresion;
use App\Models\User;
use App\Models\VentaFiscalPos;
use App\Services\CobroService;
use App\Services\PedidoService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FiscalContingenciaTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Establecimiento $establishment;

    private Mesa $table;

    private Producto $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesPermissionsSeeder::class);

        $this->cashier = User::factory()->create(['usuario' => '31', 'password' => '1234']);
        $this->cashier->assignRole('cajero');

        $this->establishment = Establecimiento::create([
            'nombre' => 'Pupusería Demo',
            'direccion' => 'Dirección de prueba',
        ]);

        $this->table = Mesa::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'numero' => '9',
            'zona' => ZonaMesa::SALON,
            'estado' => EstadoMesa::LIBRE,
        ]);

        $category = Categoria::create(['nombre' => 'Bebidas Frías']);
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
            'tipo' => 'TICKET',
            'activa' => true,
            'configuracion' => ['driver' => 'queue'],
        ]);

        ConfiguracionFiscal::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'fiscal_habilitada' => true,
            'cliente_key' => 'est-test',
            'cliente_secret' => 'secret',
            'intentos_maximos' => 3,
        ]);
    }

    private function pedidoListoParaCobro(): \App\Models\Pedido
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::MESA, $this->cashier, $this->table->getKey());
        $service->addProduct($pedido, $this->product, $this->cashier);
        $service->sendPendingBatch($pedido, $this->cashier);
        $service->sendToCashRegister($pedido, $this->cashier);

        return $pedido->fresh();
    }

    public function test_sin_conectividad_la_venta_es_contingencia_y_el_ticket_es_transitorio(): void
    {
        Queue::fake();
        // Puerto cerrado → la sonda de conectividad falla → contingencia.
        config(['fiscal.url' => 'http://127.0.0.1:1']);

        $pedido = $this->pedidoListoParaCobro();

        app(CobroService::class)->chargeAndSend($pedido, MetodoPago::EFECTIVO, '10.00', $this->cashier);

        $venta = VentaFiscalPos::query()->where('pedido_id', $pedido->getKey())->firstOrFail();
        $cola = $venta->cola;

        $this->assertSame(EstadoVentaFiscal::NO->value, $venta->estado->value);
        $this->assertNotNull($venta->codigo_generacion);
        $this->assertMatchesRegularExpression(
            '/^[A-F0-9]{8}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{12}$/',
            (string) $venta->codigo_generacion,
        );
        $this->assertNotNull($venta->fecha_emision);

        $this->assertTrue($cola->contingencia);
        $this->assertNotNull($cola->contingencia_desde);
        $this->assertSame(2, $cola->payload_envio['tipoModelo']);
        $this->assertSame(2, $cola->payload_envio['tipoOperacion']);
        $this->assertSame(3, $cola->payload_envio['tipoContingencia']);
        $this->assertSame($venta->codigo_generacion, $cola->payload_envio['codigo_generacion']);

        $ticket = TrabajoImpresion::query()
            ->where('pedido_id', $pedido->getKey())
            ->where('tipo_trabajo', 'TICKET')
            ->firstOrFail();

        $this->assertStringContainsString('MODELO DE FACTURACIÓN: DIFERIDO', (string) $ticket->contenido);
        $this->assertStringContainsString('TIPO DE TRANSMISIÓN: POR CONTINGENCIA', (string) $ticket->contenido);
        $this->assertStringContainsString((string) $venta->codigo_generacion, (string) $ticket->contenido);
    }

    public function test_reconciliacion_marca_sincronizada_y_encola_el_dte_final(): void
    {
        Queue::fake();
        config(['fiscal.url' => 'http://127.0.0.1:1']);

        $pedido = $this->pedidoListoParaCobro();
        app(CobroService::class)->charge($pedido, MetodoPago::EFECTIVO, '10.00', $this->cashier);

        $venta = VentaFiscalPos::query()->where('pedido_id', $pedido->getKey())->firstOrFail();

        Http::fake(['*' => Http::response([
            'codigoGeneracion' => $venta->codigo_generacion,
            'numeroControl' => 'DTE-01-M001P001-000000000000001',
            'estadoFiscal' => 'SEALED',
            'selloRecepcion' => 'SELLO-123',
        ], 200)]);

        $selladas = app(FiscalOutboxService::class)->reconciliar();

        $this->assertSame(1, $selladas);

        $venta->refresh();
        $this->assertSame(EstadoVentaFiscal::SINCRONIZADO->value, $venta->estado->value);
        $this->assertSame('SELLO-123', $venta->sello_recepcion);
        $this->assertSame('DTE-01-M001P001-000000000000001', $venta->numero_control);
        $this->assertSame('ENVIADO', $venta->cola->fresh()->estado->value);

        $final = TrabajoImpresion::query()
            ->where('pedido_id', $pedido->getKey())
            ->where('motivo_reimpresion', 'like', 'DTE final%')
            ->first();

        $this->assertNotNull($final);
        $this->assertStringContainsString('SELLO-123', (string) $final->contenido);
    }
}
