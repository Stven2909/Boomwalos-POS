<?php

namespace Tests\Feature\Informes;

use App\Enums\EstadoCocina;
use App\Enums\EstadoMesa;
use App\Enums\OrigenPedido;
use App\Enums\TipoPedido;
use App\Enums\ZonaMesa;
use App\Models\Establecimiento;
use App\Models\EventoAuditoria;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\TandaPedido;
use App\Models\User;
use App\Services\ReportesService;
use Carbon\Carbon;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CocinaReportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Establecimiento $establishment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrador');

        $this->establishment = Establecimiento::create(['nombre' => 'Cocina Test', 'direccion' => 'Centro']);

        $this->actingAs($this->admin);
    }

    private function createOrder(): Pedido
    {
        $mesa = Mesa::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'numero' => (string) random_int(1, 999),
            'zona' => ZonaMesa::SALON,
            'estado' => EstadoMesa::LIBRE,
            'activa' => true,
        ]);

        return Pedido::create([
            'numero_seguimiento' => 'COC-'.random_int(1000, 9999),
            'tipo_pedido' => TipoPedido::MESA,
            'mesa_id' => $mesa->getKey(),
            'establecimiento_id' => $this->establishment->getKey(),
            'usuario_id' => $this->admin->getKey(),
            'origen_pedido' => OrigenPedido::CAJA,
            'codigo_corto' => random_int(1, 999),
            'fecha_codigo' => now()->toDateString(),
            'estado_comercial' => \App\Enums\EstadoComercialPedido::COBRADO,
        ]);
    }

    public function test_cocina_tiempos_promedio_calculates_correctly(): void
    {
        $pedido = $this->createOrder();
        $tanda = TandaPedido::create([
            'pedido_id' => $pedido->getKey(),
            'numero_tanda' => 1,
            'estado_cocina' => EstadoCocina::ENTREGADA,
        ]);

        $base = now()->subHours(3);

        $sendEvent = EventoAuditoria::create([
            'entidad_tipo' => Pedido::class,
            'entidad_id' => $pedido->getKey(),
            'usuario_id' => $this->admin->getKey(),
            'tipo_evento' => 'pedido_enviado_cocina',
            'payload' => ['tanda_id' => $tanda->getKey()],
        ]);
        DB::table('evento_auditorias')->where('id', $sendEvent->getKey())->update(['created_at' => $base->toDateTimeString()]);

        $prepEvent = EventoAuditoria::create([
            'entidad_tipo' => TandaPedido::class,
            'entidad_id' => $tanda->getKey(),
            'usuario_id' => $this->admin->getKey(),
            'tipo_evento' => 'tanda_iniciada_preparacion',
            'payload' => ['estado_anterior' => 'PENDIENTE', 'estado_nuevo' => 'EN_PREPARACION'],
        ]);
        DB::table('evento_auditorias')->where('id', $prepEvent->getKey())->update(['created_at' => $base->copy()->addMinutes(5)->toDateTimeString()]);

        $listaEvent = EventoAuditoria::create([
            'entidad_tipo' => TandaPedido::class,
            'entidad_id' => $tanda->getKey(),
            'usuario_id' => $this->admin->getKey(),
            'tipo_evento' => 'tanda_marcada_lista',
            'payload' => ['estado_anterior' => 'EN_PREPARACION', 'estado_nuevo' => 'LISTA'],
        ]);
        DB::table('evento_auditorias')->where('id', $listaEvent->getKey())->update(['created_at' => $base->copy()->addMinutes(15)->toDateTimeString()]);

        $entregEvent = EventoAuditoria::create([
            'entidad_tipo' => TandaPedido::class,
            'entidad_id' => $tanda->getKey(),
            'usuario_id' => $this->admin->getKey(),
            'tipo_evento' => 'tanda_entregada',
            'payload' => ['estado_anterior' => 'LISTA', 'estado_nuevo' => 'ENTREGADA'],
        ]);
        DB::table('evento_auditorias')->where('id', $entregEvent->getKey())->update(['created_at' => $base->copy()->addMinutes(20)->toDateTimeString()]);

        $service = app(ReportesService::class);
        $tiempos = $service->cocinaTiemposPromedio(
            now()->subHours(5),
            now()->endOfDay(),
        );

        $this->assertEquals(1, $tiempos['total_completadas']);
        $this->assertEquals(5.0, $tiempos['pendiente_preparacion']);
        $this->assertEquals(10.0, $tiempos['preparacion_lista']);
        $this->assertEquals(5.0, $tiempos['lista_entregada']);
    }

    public function test_cocina_tiempos_promedio_returns_zeros_when_no_complete_chain(): void
    {
        $pedido = $this->createOrder();
        $tanda = TandaPedido::create([
            'pedido_id' => $pedido->getKey(),
            'numero_tanda' => 1,
            'estado_cocina' => EstadoCocina::EN_PREPARACION,
        ]);

        $sendEvent = EventoAuditoria::create([
            'entidad_tipo' => Pedido::class,
            'entidad_id' => $pedido->getKey(),
            'usuario_id' => $this->admin->getKey(),
            'tipo_evento' => 'pedido_enviado_cocina',
            'payload' => ['tanda_id' => $tanda->getKey()],
        ]);
        DB::table('evento_auditorias')->where('id', $sendEvent->getKey())->update(['created_at' => now()->subHours(2)->toDateTimeString()]);

        $service = app(ReportesService::class);
        $tiempos = $service->cocinaTiemposPromedio(
            now()->subHours(5),
            now()->endOfDay(),
        );

        $this->assertEquals(0, $tiempos['total_completadas']);
        $this->assertEquals(0.0, $tiempos['pendiente_preparacion']);
    }

    public function test_cocina_volumen_returns_state_counts(): void
    {
        $pedido1 = $this->createOrder();
        $pedido2 = $this->createOrder();

        TandaPedido::create([
            'pedido_id' => $pedido1->getKey(),
            'numero_tanda' => 1,
            'estado_cocina' => EstadoCocina::EN_PREPARACION,
        ]);
        TandaPedido::create([
            'pedido_id' => $pedido2->getKey(),
            'numero_tanda' => 1,
            'estado_cocina' => EstadoCocina::ENTREGADA,
        ]);

        $service = app(ReportesService::class);
        $volumen = $service->cocinaVolumen(
            now()->subDays(3)->startOfDay(),
            now()->endOfDay(),
        );

        $this->assertEquals(2, $volumen['total_tandas']);
        $this->assertArrayHasKey('EN_PREPARACION', $volumen['por_estado']);
        $this->assertArrayHasKey('ENTREGADA', $volumen['por_estado']);
    }

    public function test_cocina_volumen_excludes_other_establishments(): void
    {
        $otherEstablishment = Establecimiento::create(['nombre' => 'Otra', 'direccion' => 'Sur']);

        $mesaOther = Mesa::create([
            'establecimiento_id' => $otherEstablishment->getKey(),
            'numero' => '999',
            'zona' => ZonaMesa::SALON,
            'estado' => EstadoMesa::LIBRE,
            'activa' => true,
        ]);

        $pedidoOther = Pedido::create([
            'numero_seguimiento' => 'COC-OTHER',
            'tipo_pedido' => TipoPedido::MESA,
            'mesa_id' => $mesaOther->getKey(),
            'establecimiento_id' => $otherEstablishment->getKey(),
            'usuario_id' => $this->admin->getKey(),
            'origen_pedido' => OrigenPedido::CAJA,
            'codigo_corto' => 888,
            'fecha_codigo' => now()->toDateString(),
            'estado_comercial' => \App\Enums\EstadoComercialPedido::COBRADO,
        ]);

        TandaPedido::create([
            'pedido_id' => $pedidoOther->getKey(),
            'numero_tanda' => 1,
            'estado_cocina' => EstadoCocina::PENDIENTE,
        ]);

        $pedidoOwn = $this->createOrder();
        TandaPedido::create([
            'pedido_id' => $pedidoOwn->getKey(),
            'numero_tanda' => 1,
            'estado_cocina' => EstadoCocina::LISTA,
        ]);

        $service = app(ReportesService::class);
        $volumen = $service->cocinaVolumen(
            now()->subDays(3)->startOfDay(),
            now()->endOfDay(),
            $this->establishment->getKey(),
        );

        $this->assertEquals(1, $volumen['total_tandas']);
    }
}
