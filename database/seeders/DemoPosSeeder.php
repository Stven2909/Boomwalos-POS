<?php

namespace Database\Seeders;

use App\Enums\DisponibilidadProducto;
use App\Enums\EstadoComercialPedido;
use App\Enums\EstadoLineaPedido;
use App\Enums\EstadoMesa;
use App\Enums\OrigenPedido;
use App\Enums\TipoPedido;
use App\Enums\TipoImpresora;
use App\Enums\ZonaMesa;
use App\Models\Categoria;
use App\Models\Combo;
use App\Models\DetallePedido;
use App\Models\Establecimiento;
use App\Models\Impresora;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\SesionCaja;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoPosSeeder extends Seeder
{
    public function run(): void
    {
        $establecimiento = Establecimiento::firstOrCreate(
            ['nombre' => 'Pupusería Demo'],
            [
                'direccion' => 'Establecimiento principal',
                'codigo_establecimiento' => null,
                'codigo_punto_venta' => null,
            ],
        );

        Impresora::firstOrCreate(
            ['nombre' => 'Cocina', 'tipo' => TipoImpresora::COMANDA],
            ['configuracion' => ['driver' => 'queue']],
        );

        $groups = [];
        foreach ([
            ['nombre' => 'Pupusas', 'descripcion' => 'Pupusas de la casa.', 'icono' => '🫓'],
            ['nombre' => 'Bebidas', 'descripcion' => 'Bebidas frías y calientes.', 'icono' => '🥤'],
        ] as $data) {
            $groups[$data['nombre']] = Categoria::firstOrCreate(
                ['nombre' => $data['nombre']],
                ['descripcion' => $data['descripcion'], 'activa' => true, 'icono' => $data['icono']],
            );

            if (is_null($groups[$data['nombre']]->icono)) {
                $groups[$data['nombre']]->update(['icono' => $data['icono']]);
            }
        }

        $categories = [];
        foreach ([
            ['nombre' => 'Pupusas Normales', 'descripcion' => 'Pupusas clásicas del menú.', 'group' => 'Pupusas'],
            ['nombre' => 'Pupusas Especiales', 'descripcion' => 'Sabores especiales.', 'group' => 'Pupusas'],
            ['nombre' => 'Bebidas Frías', 'descripcion' => 'Bebidas frías y refrescos.', 'group' => 'Bebidas'],
            ['nombre' => 'Bebidas Calientes', 'descripcion' => 'Café e infusiones.', 'group' => 'Bebidas'],
        ] as $data) {
            $cat = Categoria::firstOrCreate(
                ['nombre' => $data['nombre']],
                [
                    'descripcion' => $data['descripcion'],
                    'activa' => true,
                    'parent_id' => $groups[$data['group']]->getKey(),
                ],
            );

            if (is_null($cat->parent_id)) {
                $cat->update(['parent_id' => $groups[$data['group']]->getKey()]);
            }

            $categories[$data['nombre']] = $cat;
        }

        Categoria::where('nombre', 'Combos')->update(['parent_id' => null, 'activa' => false]);

        $pupusaIds = Producto::query()
            ->whereIn('nombre', ['Pupusa de queso', 'Pupusa revuelta', 'Pupusa con chicharrón'])
            ->pluck('id')
            ->all();

        if ($pupusaIds !== []) {
            $combo = Combo::firstOrCreate(
                ['nombre' => 'Combo 10 pupusas'],
                [
                    'precio_fijo' => 15.00,
                    'disponibilidad' => DisponibilidadProducto::DISPONIBLE,
                ],
            );

            $option = $combo->opcionesCombo()->firstOrCreate(
                ['nombre' => 'Pupusas'],
                [
                    'cantidad_requerida' => 10,
                    'es_obligatorio' => true,
                ],
            );

            $option->productos()->sync($pupusaIds);
        }

        foreach ([
            ['categoria' => 'Pupusas Normales', 'nombre' => 'Pupusa de queso', 'precio' => 1.50],
            ['categoria' => 'Pupusas Normales', 'nombre' => 'Pupusa revuelta', 'precio' => 1.75],
            ['categoria' => 'Pupusas Especiales', 'nombre' => 'Pupusa con chicharrón', 'precio' => 2.00],
            ['categoria' => 'Bebidas Frías', 'nombre' => 'Limonada fresca', 'precio' => 4.00],
            ['categoria' => 'Bebidas Frías', 'nombre' => 'Horchata de la casa', 'precio' => 3.50],
            ['categoria' => 'Bebidas Calientes', 'nombre' => 'Café de olla', 'precio' => 2.50],
        ] as $data) {
            Producto::firstOrCreate(
                ['nombre' => $data['nombre'], 'categoria_id' => $categories[$data['categoria']]->getKey()],
                [
                    'precio' => $data['precio'],
                    'disponibilidad' => DisponibilidadProducto::DISPONIBLE,
                ],
            );
        }

        $pupusaIds = Producto::query()
            ->whereIn('categoria_id', [
                $categories['Pupusas Normales']->getKey(),
                $categories['Pupusas Especiales']->getKey(),
            ])
            ->pluck('id')
            ->all();

        if ($pupusaIds !== []) {
            $combo = Combo::firstOrCreate(
                ['nombre' => 'Combo 10 pupusas'],
                [
                    'precio_fijo' => 15.00,
                    'disponibilidad' => DisponibilidadProducto::DISPONIBLE,
                ],
            );

            $option = $combo->opcionesCombo()->firstOrCreate(
                ['nombre' => 'Pupusas'],
                [
                    'cantidad_requerida' => 10,
                    'es_obligatorio' => true,
                ],
            );

            $option->productos()->sync($pupusaIds);
        }

        foreach ([
            ZonaMesa::SALON->value => range(1, 8),
            ZonaMesa::TERRAZA->value => range(9, 12),
            ZonaMesa::BAR->value => range(13, 16),
        ] as $zona => $numbers) {
            foreach ($numbers as $number) {
                Mesa::firstOrCreate(
                    ['numero' => (string) $number],
                    [
                        'establecimiento_id' => $establecimiento->getKey(),
                        'zona' => $zona,
                        'estado' => EstadoMesa::LIBRE,
                        'activa' => true,
                    ],
                );
            }
        }

        $admin = User::role('administrador')->first() ?? User::firstOrFail();
        $cashier = User::role('cajero')->first();

        if ($cashier) {
            $cashier->establecimientos()->syncWithoutDetaching([$establecimiento->getKey()]);
        }

        if (! SesionCaja::query()->whereNull('fecha_cierre')->exists()) {
            SesionCaja::create([
                'establecimiento_id' => $establecimiento->getKey(),
                'usuario_apertura_id' => $admin->getKey(),
                'monto_inicial' => 0,
                'fecha_apertura' => now(),
            ]);
        }

        $mesaDemo = Mesa::query()
            ->where('establecimiento_id', $establecimiento->getKey())
            ->where('numero', '3')
            ->first();
        $productoDemo = Producto::query()->where('nombre', 'Limonada fresca')->first();

        if ($mesaDemo && $productoDemo) {
            $pedido = Pedido::firstOrCreate(
                ['numero_seguimiento' => 'POS-DEMO-0001'],
                [
                    'tipo_pedido' => TipoPedido::MESA,
                    'mesa_id' => $mesaDemo->getKey(),
                    'establecimiento_id' => $establecimiento->getKey(),
                    'usuario_id' => $admin->getKey(),
                    'origen_pedido' => OrigenPedido::CAJA,
                    'codigo_corto' => 1,
                    'fecha_codigo' => now()->toDateString(),
                    'estado_comercial' => EstadoComercialPedido::ABIERTO,
                ],
            );

            $mesaDemo->update(['estado' => EstadoMesa::OCUPADA]);

            DetallePedido::firstOrCreate(
                ['pedido_id' => $pedido->getKey(), 'producto_id' => $productoDemo->getKey()],
                [
                    'tanda_id' => null,
                    'estado_linea' => EstadoLineaPedido::ACTIVA,
                    'cantidad' => 1,
                    'precio_unitario' => $productoDemo->precio,
                ],
            );
        }

        $this->seedCodigoCortoSequence($establecimiento->getKey());

        $pedidoPendiente = Pedido::firstOrCreate(
            ['numero_seguimiento' => 'POS-DEMO-0002'],
            [
                'tipo_pedido' => TipoPedido::PARA_LLEVAR,
                'mesa_id' => null,
                'establecimiento_id' => $establecimiento->getKey(),
                'usuario_id' => $admin->getKey(),
                'origen_pedido' => OrigenPedido::DISPOSITIVO,
                'codigo_corto' => 2,
                'fecha_codigo' => now()->toDateString(),
                'estado_comercial' => EstadoComercialPedido::PENDIENTE_COBRO,
            ],
        );

        if (! $pedidoPendiente->detalles()->exists()) {
            DetallePedido::create([
                'pedido_id' => $pedidoPendiente->getKey(),
                'tanda_id' => null,
                'estado_linea' => EstadoLineaPedido::ACTIVA,
                'producto_id' => $productoDemo->getKey(),
                'combo_id' => null,
                'cantidad' => 2,
                'precio_unitario' => $productoDemo->precio,
            ]);
        }
    }

    private function seedCodigoCortoSequence(int $establecimientoId): void
    {
        $fecha = now()->toDateString();

        if (DB::table('secuencias_pedidos')
            ->where('establecimiento_id', $establecimientoId)
            ->where('fecha', $fecha)
            ->doesntExist()) {
            DB::table('secuencias_pedidos')->insert([
                'establecimiento_id' => $establecimientoId,
                'fecha' => $fecha,
                'ultimo_valor' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
