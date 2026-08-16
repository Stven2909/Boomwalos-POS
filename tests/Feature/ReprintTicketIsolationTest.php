<?php

namespace Tests\Feature;

use App\Application\Printing\QueueTicketResult;
use App\Application\Printing\ReprintTicket;
use App\Contracts\EstablishmentContextInterface;
use App\Enums\EstadoComercialPedido;
use App\Enums\EstadoImpresion;
use App\Enums\OrigenPedido;
use App\Enums\TipoImpresora;
use App\Enums\TipoPedido;
use App\Enums\TipoTrabajoImpresion;
use App\Models\Establecimiento;
use App\Models\Impresora;
use App\Models\Pedido;
use App\Models\TrabajoImpresion;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * La reimpresión de tickets queda protegida por la sucursal activa: un pedido
 * de otra sucursal se rechaza con AuthorizationException y no encola trabajo
 * de impresión; un pedido de la sucursal activa sí crea la reimpresión.
 */
class ReprintTicketIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermissionsSeeder::class);
    }

    private function establishContext(Establecimiento $establecimiento): void
    {
        app(EstablishmentContextInterface::class)->set((int) $establecimiento->getKey());
    }

    private function pedidoPara(Establecimiento $establecimiento, User $usuario): Pedido
    {
        return Pedido::create([
            'numero_seguimiento' => 'TRK-'.Str::upper(Str::random(8)),
            'tipo_pedido' => TipoPedido::PARA_LLEVAR,
            'establecimiento_id' => $establecimiento->getKey(),
            'usuario_id' => $usuario->getKey(),
            'origen_pedido' => OrigenPedido::CAJA,
            'estado_comercial' => EstadoComercialPedido::ABIERTO,
        ]);
    }

    public function test_reimpresion_de_pedido_de_otra_sucursal_es_denegada_y_no_crea_trabajo(): void
    {
        $sucursalActiva = Establecimiento::create(['nombre' => 'Centro', 'direccion' => 'Centro']);
        $otraSucursal = Establecimiento::create(['nombre' => 'Norte', 'direccion' => 'Norte']);
        $cajero = User::factory()->create();
        $this->establishContext($sucursalActiva);

        $pedidoDeOtraSucursal = $this->pedidoPara($otraSucursal, $cajero);

        try {
            app(ReprintTicket::class)->handle($pedidoDeOtraSucursal, $cajero);
            $this->fail('Se esperaba una AuthorizationException.');
        } catch (AuthorizationException $exception) {
            $this->assertSame('No puedes reimprimir tickets de otra sucursal.', $exception->getMessage());
        }

        $this->assertDatabaseCount('trabajo_impresion', 0);
    }

    public function test_reimpresion_de_pedido_de_la_sucursal_activa_crea_el_trabajo(): void
    {
        $sucursal = Establecimiento::create(['nombre' => 'Centro', 'direccion' => 'Centro']);
        $cajero = User::factory()->create();
        $this->establishContext($sucursal);

        $impresora = Impresora::create([
            'nombre' => 'Ticket',
            'tipo' => TipoImpresora::TICKET->value,
            'configuracion' => ['driver' => 'queue'],
        ]);

        $pedido = $this->pedidoPara($sucursal, $cajero);

        TrabajoImpresion::create([
            'impresora_id' => $impresora->getKey(),
            'pedido_id' => $pedido->getKey(),
            'tipo_trabajo' => TipoTrabajoImpresion::TICKET->value,
            'es_reimpresion' => false,
            'estado' => EstadoImpresion::PENDIENTE->value,
            'contenido' => 'TICKET ORIGINAL',
        ]);

        $result = app(ReprintTicket::class)->handle($pedido, $cajero);

        $this->assertSame(QueueTicketResult::CREATED, $result->status);
        $this->assertDatabaseCount('trabajo_impresion', 2);

        $reimpresion = TrabajoImpresion::query()->where('es_reimpresion', true)->first();
        $this->assertNotNull($reimpresion);
        $this->assertSame($pedido->getKey(), $reimpresion->pedido_id);
    }
}
