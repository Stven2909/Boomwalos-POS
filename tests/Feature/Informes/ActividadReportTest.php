<?php

namespace Tests\Feature\Informes;

use App\Enums\EstadoComercialPedido;
use App\Enums\EstadoMesa;
use App\Enums\OrigenPedido;
use App\Enums\TipoPedido;
use App\Enums\ZonaMesa;
use App\Models\Establecimiento;
use App\Models\EventoAuditoria;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\User;
use App\Services\ReportesService;
use Carbon\Carbon;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActividadReportTest extends TestCase
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

        $this->establishment = Establecimiento::create(['nombre' => 'Actividad Test', 'direccion' => 'Centro']);

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
            'numero_seguimiento' => 'ACT-'.random_int(1000, 9999),
            'tipo_pedido' => TipoPedido::MESA,
            'mesa_id' => $mesa->getKey(),
            'establecimiento_id' => $this->establishment->getKey(),
            'usuario_id' => $this->admin->getKey(),
            'origen_pedido' => OrigenPedido::CAJA,
            'codigo_corto' => random_int(1, 999),
            'fecha_codigo' => now()->toDateString(),
            'estado_comercial' => EstadoComercialPedido::COBRADO,
        ]);
    }

    public function test_actividad_returns_paginated_events(): void
    {
        $pedido = $this->createOrder();

        $ev1 = EventoAuditoria::create([
            'entidad_tipo' => Pedido::class,
            'entidad_id' => $pedido->getKey(),
            'usuario_id' => $this->admin->getKey(),
            'tipo_evento' => 'pedido_cobrado',
            'payload' => ['metodo_pago' => 'EFECTIVO'],
        ]);
        DB::table('evento_auditorias')->where('id', $ev1->getKey())->update(['created_at' => now()->subHours(2)->toDateTimeString()]);

        $ev2 = EventoAuditoria::create([
            'entidad_tipo' => Pedido::class,
            'entidad_id' => $pedido->getKey(),
            'usuario_id' => $this->admin->getKey(),
            'tipo_evento' => 'pedido_cerrado',
            'payload' => [],
        ]);
        DB::table('evento_auditorias')->where('id', $ev2->getKey())->update(['created_at' => now()->subHours(1)->toDateTimeString()]);

        $service = app(ReportesService::class);
        $result = $service->actividad(
            now()->subDays(3),
            now()->endOfDay(),
        );

        $this->assertEquals(2, $result->total());
        $this->assertCount(2, $result->items());
    }

    public function test_actividad_filters_by_tipo_evento(): void
    {
        $pedido = $this->createOrder();

        $ev1 = EventoAuditoria::create([
            'entidad_tipo' => Pedido::class,
            'entidad_id' => $pedido->getKey(),
            'usuario_id' => $this->admin->getKey(),
            'tipo_evento' => 'pedido_cobrado',
            'payload' => [],
        ]);
        DB::table('evento_auditorias')->where('id', $ev1->getKey())->update(['created_at' => now()->subHours(1)->toDateTimeString()]);

        $ev2 = EventoAuditoria::create([
            'entidad_tipo' => Pedido::class,
            'entidad_id' => $pedido->getKey(),
            'usuario_id' => $this->admin->getKey(),
            'tipo_evento' => 'pedido_cerrado',
            'payload' => [],
        ]);
        DB::table('evento_auditorias')->where('id', $ev2->getKey())->update(['created_at' => now()->subHours(1)->toDateTimeString()]);

        $service = app(ReportesService::class);
        $result = $service->actividad(
            now()->subDays(3),
            now()->endOfDay(),
            tipoEvento: 'pedido_cobrado',
        );

        $this->assertEquals(1, $result->total());
        $this->assertEquals('pedido_cobrado', $result->items()[0]->tipo_evento);
    }

    public function test_actividad_filters_by_usuario(): void
    {
        $otherUser = User::factory()->create();
        $pedido = $this->createOrder();

        $ev1 = EventoAuditoria::create([
            'entidad_tipo' => Pedido::class,
            'entidad_id' => $pedido->getKey(),
            'usuario_id' => $this->admin->getKey(),
            'tipo_evento' => 'pedido_cobrado',
            'payload' => [],
        ]);
        DB::table('evento_auditorias')->where('id', $ev1->getKey())->update(['created_at' => now()->subHours(1)->toDateTimeString()]);

        $ev2 = EventoAuditoria::create([
            'entidad_tipo' => Pedido::class,
            'entidad_id' => $pedido->getKey(),
            'usuario_id' => $otherUser->getKey(),
            'tipo_evento' => 'pedido_cobrado',
            'payload' => [],
        ]);
        DB::table('evento_auditorias')->where('id', $ev2->getKey())->update(['created_at' => now()->subHours(1)->toDateTimeString()]);

        $service = app(ReportesService::class);
        $result = $service->actividad(
            now()->subDays(3),
            now()->endOfDay(),
            usuarioId: $this->admin->getKey(),
        );

        $this->assertEquals(1, $result->total());
    }

    public function test_actividad_excludes_events_outside_date_range(): void
    {
        $pedido = $this->createOrder();

        $ev = EventoAuditoria::create([
            'entidad_tipo' => Pedido::class,
            'entidad_id' => $pedido->getKey(),
            'usuario_id' => $this->admin->getKey(),
            'tipo_evento' => 'pedido_cobrado',
            'payload' => [],
        ]);
        DB::table('evento_auditorias')->where('id', $ev->getKey())->update(['created_at' => now()->subDays(10)->toDateTimeString()]);

        $service = app(ReportesService::class);
        $result = $service->actividad(
            now()->subDays(3),
            now()->endOfDay(),
        );

        $this->assertEquals(0, $result->total());
    }

    public function test_actividad_respects_per_page_limit(): void
    {
        $pedido = $this->createOrder();

        for ($i = 0; $i < 5; $i++) {
            $ev = EventoAuditoria::create([
                'entidad_tipo' => Pedido::class,
                'entidad_id' => $pedido->getKey(),
                'usuario_id' => $this->admin->getKey(),
                'tipo_evento' => 'pedido_cobrado',
                'payload' => ['index' => $i],
            ]);
            DB::table('evento_auditorias')->where('id', $ev->getKey())->update(['created_at' => now()->subHours(1)->toDateTimeString()]);
        }

        $service = app(ReportesService::class);
        $result = $service->actividad(
            now()->subDays(3),
            now()->endOfDay(),
            perPage: 3,
        );

        $this->assertEquals(5, $result->total());
        $this->assertCount(3, $result->items());
        $this->assertEquals(2, $result->lastPage());
    }
}
