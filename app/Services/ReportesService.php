<?php

namespace App\Services;

use App\Contracts\EstablishmentContextInterface;
use App\Enums\EstadoComercialPedido;
use App\Enums\EstadoLineaPedido;
use App\Enums\MetodoPago;
use App\Models\DetallePedido;
use App\Models\EventoAuditoria;
use App\Models\Pago;
use App\Models\SesionCaja;
use App\Models\TandaPedido;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class ReportesService
{
    public function __construct(
        private readonly EstablishmentContextInterface $establishmentContext,
    ) {}

    private function ensureAccessibleEstablishment(int $establecimientoId): void
    {
        if (! $this->establishmentContext->canAccess($establecimientoId)) {
            abort(403, 'No tienes acceso a este establecimiento.');
        }
    }

    private function resolveEstablecimientoIds(?int $establecimientoId): array
    {
        if ($establecimientoId !== null) {
            $this->ensureAccessibleEstablishment($establecimientoId);

            return [$establecimientoId];
        }

        return $this->establishmentContext->accessible()->pluck('id')->all();
    }

    // ── Ventas ───────────────────────────────────────────────

    public function ventasResumen(Carbon $desde, Carbon $hasta, ?int $establecimientoId = null): array
    {
        $ids = $this->resolveEstablecimientoIds($establecimientoId);

        $result = DetallePedido::query()
            ->where('detalles_pedido.estado_linea', EstadoLineaPedido::ACTIVA->value)
            ->whereHas('pedido', function (Builder $q) use ($desde, $hasta, $ids) {
                $q->whereIn('pedidos.estado_comercial', [
                        EstadoComercialPedido::COBRADO->value,
                        EstadoComercialPedido::CERRADO->value,
                    ])
                    ->whereBetween('pedidos.created_at', [$desde, $hasta])
                    ->whereIn('pedidos.establecimiento_id', $ids);
            })
            ->selectRaw('SUM(detalles_pedido.precio_unitario * detalles_pedido.cantidad) as total_ventas, COUNT(DISTINCT detalles_pedido.pedido_id) as cantidad_pedidos')
            ->first();

        $total = (float) ($result->total_ventas ?? 0);
        $cantidad = (int) ($result->cantidad_pedidos ?? 0);

        return [
            'total_ventas' => $total,
            'cantidad_pedidos' => $cantidad,
            'ticket_promedio' => $cantidad > 0 ? round($total / $cantidad, 2) : 0.0,
        ];
    }

    public function ventasPorMetodoPago(Carbon $desde, Carbon $hasta, ?int $establecimientoId = null): SupportCollection
    {
        $ids = $this->resolveEstablecimientoIds($establecimientoId);

        $totales = Pago::query()
            ->whereHas('pedido', function (Builder $q) use ($desde, $hasta, $ids) {
                $q->whereIn('pedidos.estado_comercial', [
                        EstadoComercialPedido::COBRADO->value,
                        EstadoComercialPedido::CERRADO->value,
                    ])
                    ->whereBetween('pedidos.created_at', [$desde, $hasta])
                    ->whereIn('pedidos.establecimiento_id', $ids);
            })
            ->selectRaw('metodo_pago, SUM(COALESCE(monto_recibido, 0) - COALESCE(cambio_devuelto, 0)) as total')
            ->groupBy('metodo_pago')
            ->pluck('total', 'metodo_pago');

        $granTotal = $totales->sum();

        return collect(MetodoPago::cases())->map(function (MetodoPago $metodo) use ($totales, $granTotal) {
            $total = (float) ($totales[$metodo->value] ?? 0);

            return [
                'metodo_pago' => $metodo->value,
                'total' => $total,
                'porcentaje' => $granTotal > 0 ? round($total / $granTotal * 100, 1) : 0.0,
            ];
        });
    }

    public function topProductos(Carbon $desde, Carbon $hasta, ?int $establecimientoId = null, int $limit = 10): SupportCollection
    {
        $ids = $this->resolveEstablecimientoIds($establecimientoId);

        $applyFilters = function (Builder $q) use ($desde, $hasta, $ids) {
            return $q
                ->where('detalles_pedido.estado_linea', EstadoLineaPedido::ACTIVA->value)
                ->whereHas('pedido', function (Builder $q) use ($desde, $hasta, $ids) {
                    $q->whereIn('pedidos.estado_comercial', [
                            EstadoComercialPedido::COBRADO->value,
                            EstadoComercialPedido::CERRADO->value,
                        ])
                        ->whereBetween('pedidos.created_at', [$desde, $hasta])
                        ->whereIn('pedidos.establecimiento_id', $ids);
                });
        };

        $productos = $applyFilters(DetallePedido::query())
            ->whereNotNull('detalles_pedido.producto_id')
            ->join('productos', 'productos.id', '=', 'detalles_pedido.producto_id')
            ->groupBy('detalles_pedido.producto_id', 'productos.nombre')
            ->selectRaw('productos.nombre as nombre, SUM(detalles_pedido.cantidad) as cantidad_vendida, SUM(detalles_pedido.precio_unitario * detalles_pedido.cantidad) as monto_total, false as es_combo')
            ->get()
            ->toBase();

        $combos = $applyFilters(DetallePedido::query())
            ->whereNotNull('detalles_pedido.combo_id')
            ->join('combos', 'combos.id', '=', 'detalles_pedido.combo_id')
            ->groupBy('detalles_pedido.combo_id', 'combos.nombre')
            ->selectRaw('combos.nombre as nombre, SUM(detalles_pedido.cantidad) as cantidad_vendida, SUM(detalles_pedido.precio_unitario * detalles_pedido.cantidad) as monto_total, true as es_combo')
            ->get()
            ->toBase();

        return $productos->merge($combos)
            ->sortByDesc('monto_total')
            ->take($limit)
            ->values();
    }

    public function ventasPorSucursal(Carbon $desde, Carbon $hasta, ?int $establecimientoId = null): Collection
    {
        $ids = $this->resolveEstablecimientoIds($establecimientoId);

        return DetallePedido::query()
            ->join('pedidos', 'pedidos.id', '=', 'detalles_pedido.pedido_id')
            ->join('establecimientos', 'establecimientos.id', '=', 'pedidos.establecimiento_id')
            ->where('detalles_pedido.estado_linea', EstadoLineaPedido::ACTIVA->value)
            ->whereIn('pedidos.estado_comercial', [
                EstadoComercialPedido::COBRADO->value,
                EstadoComercialPedido::CERRADO->value,
            ])
            ->whereBetween('pedidos.created_at', [$desde, $hasta])
            ->whereIn('pedidos.establecimiento_id', $ids)
            ->groupBy('pedidos.establecimiento_id', 'establecimientos.nombre')
            ->selectRaw('pedidos.establecimiento_id, establecimientos.nombre, SUM(detalles_pedido.precio_unitario * detalles_pedido.cantidad) as total')
            ->get();
    }

    // ── Caja ─────────────────────────────────────────────────

    public function sesionesCerradas(Carbon $desde, Carbon $hasta, ?int $establecimientoId = null): Collection
    {
        $ids = $this->resolveEstablecimientoIds($establecimientoId);

        return SesionCaja::query()
            ->whereNotNull('fecha_cierre')
            ->whereBetween('fecha_cierre', [$desde, $hasta])
            ->whereIn('establecimiento_id', $ids)
            ->with(['usuarioApertura', 'usuarioCierre', 'establecimiento'])
            ->orderByDesc('fecha_cierre')
            ->get([
                'id', 'usuario_apertura_id', 'usuario_cierre_id', 'establecimiento_id',
                'fecha_apertura', 'fecha_cierre', 'monto_inicial',
                'efectivo_esperado', 'efectivo_contado', 'diferencia',
                'total_efectivo', 'total_tarjeta', 'total_ventas',
            ]);
    }

    public function sesionDetallePagos(int $sesionCajaId): Collection
    {
        $sesion = SesionCaja::findOrFail($sesionCajaId);
        $this->ensureAccessibleEstablishment($sesion->establecimiento_id);

        return Pago::query()
            ->where('sesion_caja_id', $sesionCajaId)
            ->orderBy('created_at')
            ->get(['pedido_id', 'metodo_pago', 'monto_recibido', 'cambio_devuelto', 'created_at']);
    }

    // ── Cocina / Tandas ──────────────────────────────────────

    public function cocinaTiemposPromedio(Carbon $desde, Carbon $hasta, ?int $establecimientoId = null): array
    {
        $ids = $this->resolveEstablecimientoIds($establecimientoId);

        $normalizeTandaId = fn (EventoAuditoria $e) => $e->tipo_evento === 'pedido_enviado_cocina'
            ? ($e->payload['tanda_id'] ?? null)
            : $e->entidad_id;

        $eventsInRange = EventoAuditoria::query()
            ->whereIn('tipo_evento', [
                'pedido_enviado_cocina',
                'tanda_iniciada_preparacion',
                'tanda_marcada_lista',
                'tanda_entregada',
            ])
            ->whereBetween('created_at', [$desde, $hasta])
            ->get();

        $tandaIds = $eventsInRange->map($normalizeTandaId)->filter()->unique()->values();

        $tandaIds = TandaPedido::query()
            ->whereIn('id', $tandaIds)
            ->whereHas('pedido', fn (Builder $q) => $q->whereIn('establecimiento_id', $ids))
            ->pluck('id');

        if ($tandaIds->isEmpty()) {
            return ['pendiente_preparacion' => 0.0, 'preparacion_lista' => 0.0, 'lista_entregada' => 0.0, 'total_completadas' => 0];
        }

        $tandaTransitions = EventoAuditoria::query()
            ->where('entidad_tipo', TandaPedido::class)
            ->whereIn('entidad_id', $tandaIds)
            ->whereIn('tipo_evento', ['tanda_iniciada_preparacion', 'tanda_marcada_lista', 'tanda_entregada'])
            ->get();

        $pedidoEnvioEvents = EventoAuditoria::query()
            ->where('tipo_evento', 'pedido_enviado_cocina')
            ->get()
            ->filter(fn (EventoAuditoria $e) => in_array($e->payload['tanda_id'] ?? null, $tandaIds->toArray(), false));

        $allEvents = $tandaTransitions->merge($pedidoEnvioEvents)->sortBy('created_at')->values();
        $grouped = $allEvents->groupBy($normalizeTandaId);

        $durations = ['pendiente_preparacion' => [], 'preparacion_lista' => [], 'lista_entregada' => []];
        $completadas = 0;

        foreach ($grouped as $events) {
            $byType = $events->sortBy('created_at')->keyBy('tipo_evento');

            if ($byType->count() < 4) {
                continue;
            }

            $completadas++;
            $durations['pendiente_preparacion'][] = $byType['pedido_enviado_cocina']->created_at->diffInSeconds($byType['tanda_iniciada_preparacion']->created_at);
            $durations['preparacion_lista'][] = $byType['tanda_iniciada_preparacion']->created_at->diffInSeconds($byType['tanda_marcada_lista']->created_at);
            $durations['lista_entregada'][] = $byType['tanda_marcada_lista']->created_at->diffInSeconds($byType['tanda_entregada']->created_at);
        }

        $avgMinutes = fn (array $seconds) => empty($seconds) ? 0.0 : round(array_sum($seconds) / count($seconds) / 60, 1);

        return [
            'pendiente_preparacion' => $avgMinutes($durations['pendiente_preparacion']),
            'preparacion_lista' => $avgMinutes($durations['preparacion_lista']),
            'lista_entregada' => $avgMinutes($durations['lista_entregada']),
            'total_completadas' => $completadas,
        ];
    }

    public function cocinaVolumen(Carbon $desde, Carbon $hasta, ?int $establecimientoId = null): array
    {
        $ids = $this->resolveEstablecimientoIds($establecimientoId);

        $porEstado = TandaPedido::query()
            ->whereHas('pedido', fn (Builder $q) => $q->whereIn('establecimiento_id', $ids))
            ->groupBy('estado_cocina')
            ->selectRaw('estado_cocina, COUNT(*) as total')
            ->pluck('total', 'estado_cocina');

        $porSucursalYPeriodo = TandaPedido::query()
            ->join('pedidos', 'pedidos.id', '=', 'tandas_pedido.pedido_id')
            ->whereIn('pedidos.establecimiento_id', $ids)
            ->whereBetween('tandas_pedido.created_at', [$desde, $hasta])
            ->groupBy('pedidos.establecimiento_id')
            ->selectRaw('pedidos.establecimiento_id, COUNT(*) as total')
            ->get();

        return [
            'por_estado' => $porEstado,
            'por_sucursal' => $porSucursalYPeriodo,
            'total_tandas' => $porSucursalYPeriodo->sum('total'),
        ];
    }

    // ── Actividad / Auditoría ────────────────────────────────

    public function actividad(
        Carbon $desde,
        Carbon $hasta,
        ?int $establecimientoId = null,
        ?int $usuarioId = null,
        ?string $tipoEvento = null,
        int $page = 1,
        int $perPage = 50,
    ): LengthAwarePaginator {
        $ids = $this->resolveEstablecimientoIds($establecimientoId);

        $query = EventoAuditoria::query()
            ->whereBetween('created_at', [$desde, $hasta])
            ->with('usuario');

        if ($usuarioId !== null) {
            $query->where('usuario_id', $usuarioId);
        }

        if ($tipoEvento !== null) {
            $query->where('tipo_evento', $tipoEvento);
        }

        $query->where(function (Builder $q) use ($ids) {
            $q->where(function (Builder $q2) use ($ids) {
                $q2->where('entidad_tipo', \App\Models\Pedido::class)
                    ->whereIn('entidad_id', \App\Models\Pedido::query()->whereIn('establecimiento_id', $ids)->pluck('id'));
            })->orWhere(function (Builder $q2) use ($ids) {
                $q2->where('entidad_tipo', TandaPedido::class)
                    ->whereIn('entidad_id', TandaPedido::query()
                        ->whereHas('pedido', fn (Builder $q3) => $q3->whereIn('establecimiento_id', $ids))
                        ->pluck('id'));
            })->orWhere(function (Builder $q2) use ($ids) {
                $q2->where('entidad_tipo', SesionCaja::class)
                    ->whereIn('entidad_id', SesionCaja::query()->whereIn('establecimiento_id', $ids)->pluck('id'));
            });
        });

        return $query->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);
    }
}
