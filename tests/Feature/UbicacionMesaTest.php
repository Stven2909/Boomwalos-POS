<?php

namespace Tests\Feature;

use App\Models\Establecimiento;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\UbicacionMesa;
use App\Models\User;
use App\Enums\EstadoMesa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UbicacionMesaTest extends TestCase
{
    use RefreshDatabase;

    private function establecimiento(string $nombre = 'Sucursal Test'): Establecimiento
    {
        return Establecimiento::create(['nombre' => $nombre, 'direccion' => 'Dir Test']);
    }

    // ------------------------------------------------------------------
    // Ubicaciones
    // ------------------------------------------------------------------

    public function test_crea_ubicacion_y_la_asigna_a_establecimiento(): void
    {
        $est = $this->establecimiento();

        $ub = UbicacionMesa::create([
            'establecimiento_id' => $est->getKey(),
            'nombre'             => 'Área Blanca',
            'orden'              => 1,
            'activa'             => true,
        ]);

        $this->assertSame('Área Blanca', $ub->nombre);
        $this->assertSame(1, $est->ubicacionesMesa()->count());
    }

    public function test_ubicaciones_se_devuelven_ordenadas(): void
    {
        $est = $this->establecimiento();

        UbicacionMesa::create(['establecimiento_id' => $est->getKey(), 'nombre' => 'Llevar',     'orden' => 5]);
        UbicacionMesa::create(['establecimiento_id' => $est->getKey(), 'nombre' => 'Área Roja',  'orden' => 3]);
        UbicacionMesa::create(['establecimiento_id' => $est->getKey(), 'nombre' => 'Área Blanca','orden' => 1]);

        $nombres = $est->ubicacionesMesa()->pluck('nombre')->all();

        $this->assertSame(['Área Blanca', 'Área Roja', 'Llevar'], $nombres);
    }

    public function test_nombre_de_ubicacion_es_unico_por_sucursal(): void
    {
        $est1 = $this->establecimiento('Sucursal A');
        $est2 = $this->establecimiento('Sucursal B');

        UbicacionMesa::create(['establecimiento_id' => $est1->getKey(), 'nombre' => 'Salón', 'orden' => 1]);
        // Misma ubicación en otra sucursal → OK
        UbicacionMesa::create(['establecimiento_id' => $est2->getKey(), 'nombre' => 'Salón', 'orden' => 1]);

        $this->assertSame(1, UbicacionMesa::where('establecimiento_id', $est1->getKey())->count());
        $this->assertSame(1, UbicacionMesa::where('establecimiento_id', $est2->getKey())->count());

        // Misma ubicación en la misma sucursal → excepción de unicidad
        $this->expectException(\Illuminate\Database\QueryException::class);
        UbicacionMesa::create(['establecimiento_id' => $est1->getKey(), 'nombre' => 'Salón', 'orden' => 2]);
    }

    // ------------------------------------------------------------------
    // Mesas – número único por sucursal
    // ------------------------------------------------------------------

    public function test_numero_de_mesa_unico_por_sucursal(): void
    {
        $est1 = $this->establecimiento('Sucursal A');
        $est2 = $this->establecimiento('Sucursal B');

        Mesa::create([
            'establecimiento_id' => $est1->getKey(),
            'numero' => '1',
            'zona'   => 'Salón',
            'estado' => EstadoMesa::LIBRE,
        ]);

        // Mismo número en otra sucursal → OK
        Mesa::create([
            'establecimiento_id' => $est2->getKey(),
            'numero' => '1',
            'zona'   => 'Salón',
            'estado' => EstadoMesa::LIBRE,
        ]);

        $this->assertSame(1, Mesa::where('establecimiento_id', $est1->getKey())->count());
        $this->assertSame(1, Mesa::where('establecimiento_id', $est2->getKey())->count());

        // Mismo número en la misma sucursal → excepción
        $this->expectException(\Illuminate\Database\QueryException::class);
        Mesa::create([
            'establecimiento_id' => $est1->getKey(),
            'numero' => '1',
            'zona'   => 'Terraza',
            'estado' => EstadoMesa::LIBRE,
        ]);
    }

    // ------------------------------------------------------------------
    // Soft delete de mesas
    // ------------------------------------------------------------------

    public function test_soft_delete_mesa_desaparece_del_query_normal(): void
    {
        $est = $this->establecimiento();

        $mesa = Mesa::create([
            'establecimiento_id' => $est->getKey(),
            'numero' => '5',
            'zona'   => 'Salón',
            'estado' => EstadoMesa::LIBRE,
        ]);

        $mesa->delete();

        $this->assertSame(0, Mesa::where('establecimiento_id', $est->getKey())->count());
        $this->assertSame(1, Mesa::withTrashed()->where('establecimiento_id', $est->getKey())->count());
    }

    public function test_pedido_historico_conserva_mesa_soft_deleted(): void
    {
        $est = $this->establecimiento();

        $mesa = Mesa::create([
            'establecimiento_id' => $est->getKey(),
            'numero' => '8',
            'zona'   => 'Salón',
            'estado' => EstadoMesa::LIBRE,
        ]);

        // Crear pedido referenciando la mesa
        $usuario = User::factory()->create();

        $pedido = Pedido::create([
            'establecimiento_id' => $est->getKey(),
            'mesa_id'            => $mesa->getKey(),
            'usuario_id'         => $usuario->getKey(),
            'tipo_pedido'        => \App\Enums\TipoPedido::MESA,
            'origen_pedido'      => \App\Enums\OrigenPedido::CAJA,
            'estado_comercial'   => \App\Enums\EstadoComercialPedido::CERRADO,
            'numero_seguimiento' => 'TEST-001',
        ]);

        // Soft-delete la mesa
        $mesa->delete();

        // El pedido sigue recuperando la mesa gracias a withTrashed()
        $pedidoFresh = Pedido::with('mesa')->find($pedido->getKey());
        $this->assertNotNull($pedidoFresh->mesa);
        $this->assertSame('8', $pedidoFresh->mesa->numero);
        $this->assertTrue($pedidoFresh->mesa->trashed());
    }

    public function test_mesa_restaurada_vuelve_a_aparecer(): void
    {
        $est = $this->establecimiento();

        $mesa = Mesa::create([
            'establecimiento_id' => $est->getKey(),
            'numero' => '3',
            'zona'   => 'Salón',
            'estado' => EstadoMesa::LIBRE,
        ]);

        $mesa->delete();
        $this->assertSame(0, Mesa::where('establecimiento_id', $est->getKey())->count());

        Mesa::withTrashed()->find($mesa->getKey())->restore();
        $this->assertSame(1, Mesa::where('establecimiento_id', $est->getKey())->count());
    }
}
