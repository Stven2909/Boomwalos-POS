<?php

namespace Database\Seeders;

use App\Enums\DisponibilidadProducto;
use App\Enums\EstadoCocina;
use App\Enums\EstadoComercialPedido;
use App\Enums\EstadoLineaPedido;
use App\Enums\EstadoMesa;
use App\Enums\MetodoPago;
use App\Enums\OrigenPedido;
use App\Enums\TipoImpresora;
use App\Enums\TipoPedido;
use App\Enums\ZonaMesa;
use App\Models\Categoria;
use App\Models\Combo;
use App\Models\DetallePedido;
use App\Models\Establecimiento;
use App\Models\EventoAuditoria;
use App\Models\Impresora;
use App\Models\Mesa;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\SesionCaja;
use App\Models\TandaPedido;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReportesTestDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@boomwalos.local')->first()
            ?? User::factory()->create(['email' => 'admin@boomwalos.local', 'name' => 'Administrador']);
        if (! $admin->hasRole('administrador')) {
            $admin->assignRole('administrador');
        }

        $cashier = User::where('email', 'cajero@boomwalos.local')->first()
            ?? User::factory()->create(['email' => 'cajero@boomwalos.local', 'name' => 'Cajero Demo']);
        if (! $cashier->hasRole('cajero')) {
            $cashier->assignRole('cajero');
        }

        // ── Establecimientos ────────────────────────────────
        $sucursalA = Establecimiento::firstOrCreate(
            ['nombre' => 'Sucursal A - Informes'],
            ['direccion' => 'Av. Principal 123'],
        );
        $sucursalB = Establecimiento::firstOrCreate(
            ['nombre' => 'Sucursal B - Informes'],
            ['direccion' => 'Calle Secundaria 456'],
        );

        $cashier->establecimientos()->syncWithoutDetaching([$sucursalA->getKey(), $sucursalB->getKey()]);

        // ── Impresora ──────────────────────────────────────
        Impresora::firstOrCreate(
            ['nombre' => 'Cocina Test', 'tipo' => TipoImpresora::COMANDA],
            ['configuracion' => ['driver' => 'queue']],
        );

        // ── Categorías y Productos ─────────────────────────
        $grupoComida = Categoria::firstOrCreate(['nombre' => 'Comida Test Group'], ['descripcion' => 'Grupo de prueba', 'activa' => true]);
        $grupoBebida = Categoria::firstOrCreate(['nombre' => 'Bebidas Test Group'], ['descripcion' => 'Grupo de prueba', 'activa' => true]);

        $catComida = Categoria::firstOrCreate(
            ['nombre' => 'Comida Test'],
            ['descripcion' => 'Platos de prueba', 'activa' => true, 'parent_id' => $grupoComida->getKey()],
        );
        if (is_null($catComida->parent_id)) {
            $catComida->update(['parent_id' => $grupoComida->getKey()]);
        }

        $catBebida = Categoria::firstOrCreate(
            ['nombre' => 'Bebidas Test'],
            ['descripcion' => 'Bebidas de prueba', 'activa' => true, 'parent_id' => $grupoBebida->getKey()],
        );
        if (is_null($catBebida->parent_id)) {
            $catBebida->update(['parent_id' => $grupoBebida->getKey()]);
        }

        $hamburguesa = Producto::firstOrCreate(
            ['nombre' => 'Hamburguesa Test', 'categoria_id' => $catComida->getKey()],
            ['precio' => 8.50, 'disponibilidad' => DisponibilidadProducto::DISPONIBLE],
        );
        $papas = Producto::firstOrCreate(
            ['nombre' => 'Papas Test', 'categoria_id' => $catComida->getKey()],
            ['precio' => 3.00, 'disponibilidad' => DisponibilidadProducto::DISPONIBLE],
        );
        $agua = Producto::firstOrCreate(
            ['nombre' => 'Agua Test', 'categoria_id' => $catBebida->getKey()],
            ['precio' => 1.00, 'disponibilidad' => DisponibilidadProducto::DISPONIBLE],
        );
        $refresco = Producto::firstOrCreate(
            ['nombre' => 'Refresco Test', 'categoria_id' => $catBebida->getKey()],
            ['precio' => 2.50, 'disponibilidad' => DisponibilidadProducto::DISPONIBLE],
        );

        // ── Combo ──────────────────────────────────────────
        $combo = Combo::firstOrCreate(
            ['nombre' => 'Combo Almuerzo Test'],
            ['precio_fijo' => 10.00, 'disponibilidad' => DisponibilidadProducto::DISPONIBLE],
        );

        // ── Mesas ──────────────────────────────────────────
        $mesaA = Mesa::firstOrCreate(
            ['numero' => 'INFO-1', 'establecimiento_id' => $sucursalA->getKey()],
            ['zona' => ZonaMesa::SALON, 'estado' => EstadoMesa::LIBRE, 'activa' => true],
        );
        $mesaB = Mesa::firstOrCreate(
            ['numero' => 'INFO-2', 'establecimiento_id' => $sucursalB->getKey()],
            ['zona' => ZonaMesa::SALON, 'estado' => EstadoMesa::LIBRE, 'activa' => true],
        );

        $this->seedCodigoCortoSequence($sucursalA->getKey());
        $this->seedCodigoCortoSequence($sucursalB->getKey());

        // ── Hace 3 días: SesionCaja + pedidos cerrados en Sucursal A ──
        $hace3 = now()->subDays(3);
        $hace2 = now()->subDays(2);
        $ayer = now()->subDay();

        $sesionA1 = $this->createClosedSession($sucursalA, $admin, $cashier, $hace3, $hace2);

        $pedidoA1 = $this->createClosedOrder($sucursalA, $admin, $mesaA, $hace3, 'INFO-001');
        $this->addDetalle($pedidoA1, $hamburguesa, 2);
        $this->chargeOrder($pedidoA1, $sesionA1, MetodoPago::EFECTIVO, 20.00, 0.00);

        $pedidoA2 = $this->createClosedOrder($sucursalA, $admin, $mesaA, $hace3, 'INFO-002');
        $this->addDetalle($pedidoA2, $papas, 1);
        $this->addDetalle($pedidoA2, $agua, 2);
        $this->chargeOrder($pedidoA2, $sesionA1, MetodoPago::TARJETA, null, 0.00);

        // ── Ayer: SesionCaja + pedidos cerrados en Sucursal B ──
        $sesionB1 = $this->createClosedSession($sucursalB, $admin, $cashier, $ayer, $ayer);

        $pedidoB1 = $this->createClosedOrder($sucursalB, $admin, $mesaB, $ayer, 'INFO-003');
        $this->addDetalle($pedidoB1, $hamburguesa, 1);
        $this->addDetalle($pedidoB1, $refresco, 1);
        $this->chargeOrder($pedidoB1, $sesionB1, MetodoPago::EFECTIVO, 15.00, 4.00);

        $pedidoB2 = $this->createClosedOrder($sucursalB, $admin, $mesaB, $ayer, 'INFO-004');
        $this->addDetalle($pedidoB2, $combo, 1);
        $this->chargeOrder($pedidoB2, $sesionB1, MetodoPago::TARJETA, null, 0.00);

        // ── Cocina: TandaPedido + eventos de transición en Sucursal A ──
        $this->seedKitchenChain($pedidoA1, $admin, $hace3);

        // ── Auditoría ──────────────────────────────────────
        EventoAuditoria::create([
            'entidad_tipo' => Pedido::class,
            'entidad_id' => $pedidoA1->getKey(),
            'usuario_id' => $admin->getKey(),
            'tipo_evento' => 'pedido_cobrado',
            'payload' => ['metodo_pago' => 'EFECTIVO'],
            'created_at' => $hace3,
        ]);
        EventoAuditoria::create([
            'entidad_tipo' => Pedido::class,
            'entidad_id' => $pedidoB1->getKey(),
            'usuario_id' => $admin->getKey(),
            'tipo_evento' => 'pedido_cobrado',
            'payload' => ['metodo_pago' => 'EFECTIVO'],
            'created_at' => $ayer,
        ]);
    }

    private function createClosedSession(Establecimiento $est, User $opener, User $closer, Carbon $apertura, Carbon $cierre): SesionCaja
    {
        return SesionCaja::create([
            'establecimiento_id' => $est->getKey(),
            'usuario_apertura_id' => $opener->getKey(),
            'usuario_cierre_id' => $closer->getKey(),
            'monto_inicial' => 100.00,
            'total_ventas' => 30.00,
            'total_efectivo' => 20.00,
            'total_tarjeta' => 10.00,
            'efectivo_esperado' => 120.00,
            'efectivo_contado' => 118.00,
            'diferencia' => -2.00,
            'fecha_apertura' => $apertura,
            'fecha_cierre' => $cierre,
        ]);
    }

    private function createClosedOrder(Establecimiento $est, User $user, Mesa $mesa, Carbon $createdAt, string $tracking): Pedido
    {
        $fechaCode = $createdAt->toDateString();

        return Pedido::create([
            'numero_seguimiento' => $tracking,
            'tipo_pedido' => TipoPedido::MESA,
            'mesa_id' => $mesa->getKey(),
            'establecimiento_id' => $est->getKey(),
            'usuario_id' => $user->getKey(),
            'origen_pedido' => OrigenPedido::CAJA,
            'codigo_corto' => (int) str_replace('INFO-', '', $tracking),
            'fecha_codigo' => $fechaCode,
            'estado_comercial' => EstadoComercialPedido::COBRADO,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function addDetalle(Pedido $pedido, Producto $producto, int $cantidad): void
    {
        DetallePedido::create([
            'pedido_id' => $pedido->getKey(),
            'tanda_id' => null,
            'estado_linea' => EstadoLineaPedido::ACTIVA,
            'producto_id' => $producto->getKey(),
            'cantidad' => $cantidad,
            'precio_unitario' => $producto->precio,
        ]);
    }

    private function chargeOrder(Pedido $pedido, SesionCaja $sesion, MetodoPago $metodo, ?float $montoRecibido, float $cambio): void
    {
        Pago::create([
            'pedido_id' => $pedido->getKey(),
            'sesion_caja_id' => $sesion->getKey(),
            'metodo_pago' => $metodo,
            'monto_recibido' => $montoRecibido ?? $pedido->total(),
            'cambio_devuelto' => $cambio,
        ]);
    }

    private function seedKitchenChain(Pedido $pedido, User $user, Carbon $base): void
    {
        $tanda = TandaPedido::create([
            'pedido_id' => $pedido->getKey(),
            'numero_tanda' => 1,
            'estado_cocina' => EstadoCocina::ENTREGADA,
        ]);

        EventoAuditoria::create([
            'entidad_tipo' => Pedido::class,
            'entidad_id' => $pedido->getKey(),
            'usuario_id' => $user->getKey(),
            'tipo_evento' => 'pedido_enviado_cocina',
            'payload' => ['tanda_id' => $tanda->getKey()],
            'created_at' => $base,
        ]);

        EventoAuditoria::create([
            'entidad_tipo' => TandaPedido::class,
            'entidad_id' => $tanda->getKey(),
            'usuario_id' => $user->getKey(),
            'tipo_evento' => 'tanda_iniciada_preparacion',
            'payload' => ['estado_anterior' => 'PENDIENTE', 'estado_nuevo' => 'EN_PREPARACION'],
            'created_at' => $base->copy()->addMinutes(5),
        ]);

        EventoAuditoria::create([
            'entidad_tipo' => TandaPedido::class,
            'entidad_id' => $tanda->getKey(),
            'usuario_id' => $user->getKey(),
            'tipo_evento' => 'tanda_marcada_lista',
            'payload' => ['estado_anterior' => 'EN_PREPARACION', 'estado_nuevo' => 'LISTA'],
            'created_at' => $base->copy()->addMinutes(15),
        ]);

        EventoAuditoria::create([
            'entidad_tipo' => TandaPedido::class,
            'entidad_id' => $tanda->getKey(),
            'usuario_id' => $user->getKey(),
            'tipo_evento' => 'tanda_entregada',
            'payload' => ['estado_anterior' => 'LISTA', 'estado_nuevo' => 'ENTREGADA'],
            'created_at' => $base->copy()->addMinutes(20),
        ]);
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
                'ultimo_valor' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
