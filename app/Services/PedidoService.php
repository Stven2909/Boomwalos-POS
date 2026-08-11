<?php

namespace App\Services;

use App\Application\Kitchen\QueueKitchenBatch;
use App\Enums\DisponibilidadProducto;
use App\Enums\EstadoCocina;
use App\Enums\EstadoComercialPedido;
use App\Enums\EstadoLineaPedido;
use App\Enums\EstadoMesa;
use App\Enums\OrigenPedido;
use App\Enums\TipoPedido;
use App\Models\Combo;
use App\Models\DetallePedido;
use App\Models\EventoAuditoria;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\SesionCaja;
use App\Models\TandaPedido;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PedidoService
{
    public function startOrder(TipoPedido $tipo, User $actor, ?int $mesaId = null, OrigenPedido $origen = OrigenPedido::CAJA): Pedido
    {
        return DB::transaction(function () use ($tipo, $actor, $mesaId, $origen): Pedido {
            $establecimientoId = $this->establishmentId();

            $this->ensureActiveCashSession($establecimientoId);

            $mesa = null;

            if ($tipo === TipoPedido::MESA) {
                if (! $mesaId) {
                    throw ValidationException::withMessages([
                        'mesa' => 'Selecciona una mesa para continuar.',
                    ]);
                }

                $mesa = Mesa::query()
                    ->where('establecimiento_id', $establecimientoId)
                    ->lockForUpdate()
                    ->find($mesaId);

                if (! $mesa) {
                    throw (new ModelNotFoundException)->setModel(Mesa::class, [$mesaId]);
                }

                $activeOrder = $mesa->pedidos()
                    ->whereIn('estado_comercial', [
                        EstadoComercialPedido::ABIERTO->value,
                        EstadoComercialPedido::PENDIENTE_COBRO->value,
                        EstadoComercialPedido::COBRADO->value,
                    ])
                    ->latest('id')
                    ->first();

                if ($activeOrder) {
                    return $activeOrder->load(['mesa', 'detalles.producto', 'detalles.tanda']);
                }

                if ($mesa->estado !== EstadoMesa::LIBRE) {
                    throw ValidationException::withMessages([
                        'mesa' => 'La mesa acaba de ser ocupada. Actualiza la pantalla e inténtalo de nuevo.',
                    ]);
                }
            }

            $codigoCorto = $this->nextCodigoCorto($establecimientoId);

            $pedido = Pedido::create([
                'numero_seguimiento' => $this->nextTrackingNumber(),
                'tipo_pedido' => $tipo,
                'mesa_id' => $mesa?->id,
                'establecimiento_id' => $establecimientoId,
                'usuario_id' => $actor->getKey(),
                'origen_pedido' => $origen,
                'codigo_corto' => $codigoCorto,
                'fecha_codigo' => now()->toDateString(),
                'estado_comercial' => EstadoComercialPedido::ABIERTO,
            ]);

            if ($mesa) {
                $mesa->update(['estado' => EstadoMesa::OCUPADA]);
            }

            $this->audit($pedido, $actor, 'pedido_creado', [
                'tipo_pedido' => $tipo->value,
                'origen_pedido' => $origen->value,
                'codigo_corto' => $codigoCorto,
                'mesa_id' => $mesa?->id,
            ]);

            return $pedido->load(['mesa', 'detalles.producto', 'detalles.tanda']);
        });
    }

    public function addProduct(Pedido $pedido, Producto $producto, User $actor): DetallePedido
    {
        return DB::transaction(function () use ($pedido, $producto, $actor): DetallePedido {
            $pedido = $this->lockPedido($pedido);
            $this->ensureEditable($pedido);

            $producto = Producto::query()->lockForUpdate()->findOrFail($producto->getKey());

            if ($producto->disponibilidad !== DisponibilidadProducto::DISPONIBLE) {
                throw ValidationException::withMessages([
                    'producto' => "{$producto->nombre} no está disponible para venta.",
                ]);
            }

            $detalle = $pedido->detalles()
                ->whereNull('tanda_id')
                ->where('estado_linea', EstadoLineaPedido::ACTIVA->value)
                ->where('producto_id', $producto->getKey())
                ->whereNull('combo_id')
                ->lockForUpdate()
                ->first();

            if ($detalle) {
                $detalle->increment('cantidad');
            } else {
                $detalle = $pedido->detalles()->create([
                    'tanda_id' => null,
                    'estado_linea' => EstadoLineaPedido::ACTIVA,
                    'producto_id' => $producto->getKey(),
                    'combo_id' => null,
                    'cantidad' => 1,
                    'precio_unitario' => $producto->precio,
                ]);
            }

            $this->audit($pedido, $actor, 'producto_agregado', [
                'producto_id' => $producto->getKey(),
                'detalle_id' => $detalle->getKey(),
            ]);

            return $detalle->fresh(['producto', 'tanda']);
        });
    }

    public function addCombo(Pedido $pedido, Combo $combo, array $selection, User $actor): DetallePedido
    {
        return DB::transaction(function () use ($pedido, $combo, $selection, $actor): DetallePedido {
            $pedido = $this->lockPedido($pedido);
            $this->ensureEditable($pedido);

            $combo = Combo::query()
                ->with('opcionesCombo.productos')
                ->lockForUpdate()
                ->findOrFail($combo->getKey());

            if ($combo->disponibilidad !== DisponibilidadProducto::DISPONIBLE) {
                throw ValidationException::withMessages([
                    'combo' => "{$combo->nombre} no está disponible para venta.",
                ]);
            }

            $normalized = $this->normalizeComboSelection($combo, $selection);
            $sameLine = $pedido->detalles()
                ->whereNull('tanda_id')
                ->where('estado_linea', EstadoLineaPedido::ACTIVA->value)
                ->where('combo_id', $combo->getKey())
                ->whereNull('producto_id')
                ->lockForUpdate()
                ->get()
                ->first(fn (DetallePedido $line): bool => $this->sameSelection($line->seleccion_combo, $normalized));

            if ($sameLine) {
                $sameLine->increment('cantidad');
                $detail = $sameLine->fresh(['combo', 'tanda']);
            } else {
                $detail = $pedido->detalles()->create([
                    'tanda_id' => null,
                    'estado_linea' => EstadoLineaPedido::ACTIVA,
                    'producto_id' => null,
                    'combo_id' => $combo->getKey(),
                    'cantidad' => 1,
                    'precio_unitario' => $combo->precio_fijo,
                    'seleccion_combo' => $normalized,
                ])->load(['combo', 'tanda']);
            }

            $this->audit($pedido, $actor, 'combo_agregado', [
                'combo_id' => $combo->getKey(),
                'detalle_id' => $detail->getKey(),
                'seleccion_combo' => $normalized,
            ]);

            return $detail;
        });
    }

    public function updatePendingCombo(Pedido $pedido, DetallePedido $detail, array $selection, User $actor): DetallePedido
    {
        return DB::transaction(function () use ($pedido, $detail, $selection, $actor): DetallePedido {
            $pedido = $this->lockPedido($pedido);
            $this->ensureEditable($pedido);

            $detail = $pedido->detalles()->whereKey($detail->getKey())->lockForUpdate()->firstOrFail();

            if (! $detail->isPending() || ! $detail->combo_id) {
                throw ValidationException::withMessages([
                    'combo' => 'Solo puedes editar combos pendientes.',
                ]);
            }

            $combo = Combo::query()->with('opcionesCombo.productos')->lockForUpdate()->findOrFail($detail->combo_id);
            $normalized = $this->normalizeComboSelection($combo, $selection);

            $otherLine = $pedido->detalles()
                ->whereNull('tanda_id')
                ->where('estado_linea', EstadoLineaPedido::ACTIVA->value)
                ->where('combo_id', $combo->getKey())
                ->whereNull('producto_id')
                ->where('id', '<>', $detail->getKey())
                ->lockForUpdate()
                ->get()
                ->first(fn (DetallePedido $line): bool => $this->sameSelection($line->seleccion_combo, $normalized));

            if ($otherLine) {
                $otherLine->increment('cantidad', $detail->cantidad);
                $detail->delete();
                $updated = $otherLine->fresh(['combo', 'tanda']);
            } else {
                $detail->update(['seleccion_combo' => $normalized]);
                $updated = $detail->fresh(['combo', 'tanda']);
            }

            $this->audit($pedido, $actor, 'combo_editado', [
                'combo_id' => $combo->getKey(),
                'detalle_id' => $updated->getKey(),
                'seleccion_combo' => $normalized,
            ]);

            return $updated;
        });
    }

    public function restorePendingCombo(Pedido $pedido, int $comboId, int $quantity, string $price, array $selection): DetallePedido
    {
        return DB::transaction(function () use ($pedido, $comboId, $quantity, $price, $selection): DetallePedido {
            $pedido = $this->lockPedido($pedido);
            $this->ensureEditable($pedido);
            $combo = Combo::query()->with('opcionesCombo.productos')->findOrFail($comboId);
            $normalized = $this->normalizeComboSelection($combo, $selection);

            $sameLine = $pedido->detalles()
                ->whereNull('tanda_id')
                ->where('estado_linea', EstadoLineaPedido::ACTIVA->value)
                ->where('combo_id', $comboId)
                ->whereNull('producto_id')
                ->lockForUpdate()
                ->get()
                ->first(fn (DetallePedido $line): bool => $this->sameSelection($line->seleccion_combo, $normalized));

            if ($sameLine) {
                $sameLine->increment('cantidad', $quantity);

                return $sameLine->fresh(['combo']);
            }

            return $pedido->detalles()->create([
                'tanda_id' => null,
                'estado_linea' => EstadoLineaPedido::ACTIVA,
                'producto_id' => null,
                'combo_id' => $comboId,
                'cantidad' => $quantity,
                'precio_unitario' => $price,
                'seleccion_combo' => $normalized,
            ])->load('combo');
        });
    }

    public function updatePendingQuantity(Pedido $pedido, DetallePedido $detalle, int $cantidad): void
    {
        DB::transaction(function () use ($pedido, $detalle, $cantidad): void {
            $pedido = $this->lockPedido($pedido);
            $this->ensureEditable($pedido);

            $detalle = $pedido->detalles()
                ->whereKey($detalle->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $detalle->isPending()) {
                throw ValidationException::withMessages([
                    'detalle' => 'Solo puedes modificar productos que aún no se han enviado a cocina.',
                ]);
            }

            if ($cantidad < 1) {
                $detalle->delete();

                return;
            }

            $detalle->update(['cantidad' => $cantidad]);
        });
    }

    public function removePendingLine(Pedido $pedido, DetallePedido $detalle): void
    {
        $this->updatePendingQuantity($pedido, $detalle, 0);
    }

    public function restorePendingLine(Pedido $pedido, int $productoId, int $cantidad, string $precioUnitario): DetallePedido
    {
        return DB::transaction(function () use ($pedido, $productoId, $cantidad, $precioUnitario): DetallePedido {
            $pedido = $this->lockPedido($pedido);
            $this->ensureEditable($pedido);

            $detalle = $pedido->detalles()
                ->whereNull('tanda_id')
                ->where('estado_linea', EstadoLineaPedido::ACTIVA->value)
                ->where('producto_id', $productoId)
                ->whereNull('combo_id')
                ->lockForUpdate()
                ->first();

            if ($detalle) {
                $detalle->increment('cantidad', $cantidad);
            } else {
                $detalle = $pedido->detalles()->create([
                    'tanda_id' => null,
                    'estado_linea' => EstadoLineaPedido::ACTIVA,
                    'producto_id' => $productoId,
                    'combo_id' => null,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precioUnitario,
                ]);
            }

            return $detalle->fresh(['producto']);
        });
    }

    public function sendPendingBatch(Pedido $pedido, User $actor): TandaPedido
    {
        return DB::transaction(function () use ($pedido, $actor): TandaPedido {
            $pedido = $this->lockPedido($pedido);
            $this->ensureEditable($pedido);

            $pendingLines = $pedido->detalles()
                ->whereNull('tanda_id')
                ->where('estado_linea', EstadoLineaPedido::ACTIVA->value)
                ->lockForUpdate()
                ->get();

            if ($pendingLines->isEmpty()) {
                $latestBatch = $pedido->tandas()->latest('id')->first();

                if ($latestBatch) {
                    return $latestBatch->load('detalles.producto');
                }

                throw ValidationException::withMessages([
                    'pedido' => 'Agrega al menos un producto nuevo antes de enviar a cocina.',
                ]);
            }

            $tanda = $this->createBatchForLockedOrder($pedido, $pendingLines, $actor);
            $this->audit($pedido, $actor, 'pedido_enviado_cocina', [
                'tanda_id' => $tanda->getKey(),
                'numero_tanda' => $tanda->numero_tanda,
                'detalles' => $pendingLines->map(fn (DetallePedido $detalle): array => [
                    'id' => $detalle->getKey(),
                    'producto_id' => $detalle->producto_id,
                    'cantidad' => $detalle->cantidad,
                ])->values()->all(),
            ]);

            return $tanda->load('detalles.producto');
        });
    }

    public function sendToCashRegister(Pedido $pedido, User $actor): ?TandaPedido
    {
        return DB::transaction(function () use ($pedido, $actor): ?TandaPedido {
            $pedido = $this->lockPedido($pedido);

            if ($pedido->estado_comercial !== EstadoComercialPedido::ABIERTO) {
                throw ValidationException::withMessages([
                    'pedido' => 'Este pedido ya fue enviado a caja.',
                ]);
            }

            $pendingLines = $pedido->detalles()
                ->whereNull('tanda_id')
                ->where('estado_linea', EstadoLineaPedido::ACTIVA->value)
                ->lockForUpdate()
                ->get();

            $tanda = null;

            if ($pendingLines->isNotEmpty()) {
            $tanda = $this->createBatchForLockedOrder($pedido, $pendingLines, $actor);

                $this->audit($pedido, $actor, 'pedido_enviado_cocina', [
                    'tanda_id' => $tanda->getKey(),
                    'numero_tanda' => $tanda->numero_tanda,
                    'detalles' => $pendingLines->map(fn (DetallePedido $detalle): array => [
                        'id' => $detalle->getKey(),
                        'producto_id' => $detalle->producto_id,
                        'cantidad' => $detalle->cantidad,
                    ])->values()->all(),
                ]);
            }

            $pedido->update(['estado_comercial' => EstadoComercialPedido::PENDIENTE_COBRO]);

            $this->audit($pedido, $actor, 'pedido_enviado_caja', [
                'tanda_id' => $tanda?->getKey(),
                'total_lineas' => $pendingLines->count(),
            ]);

            return $tanda?->load('detalles.producto');
        });
    }

    private function createBatchForLockedOrder(Pedido $pedido, $pendingLines, User $actor): TandaPedido
    {
        $numeroTanda = ((int) $pedido->tandas()->max('numero_tanda')) + 1;
        $tanda = $pedido->tandas()->create([
            'numero_tanda' => $numeroTanda,
            'estado_cocina' => EstadoCocina::PENDIENTE,
        ]);

        $pedido->detalles()
            ->whereIn('id', $pendingLines->modelKeys())
            ->update(['tanda_id' => $tanda->getKey()]);

        $printJob = app(QueueKitchenBatch::class)->handle($tanda);

        $this->audit($pedido, $actor, $printJob ? 'comanda_en_cola' : 'comanda_sin_impresora', [
            'tanda_id' => $tanda->getKey(),
            'trabajo_impresion_id' => $printJob?->getKey(),
        ]);

        return $tanda;
    }

    public function cancelSentLine(DetallePedido $detalle, User $actor, string $motivo = 'Anulación desde Punto de Venta'): void
    {
        if (! $actor->can('cancelar_pedido')) {
            throw new AuthorizationException('No tienes permiso para anular productos enviados a cocina.');
        }

        DB::transaction(function () use ($detalle, $actor, $motivo): void {
            $detalle = DetallePedido::query()->lockForUpdate()->findOrFail($detalle->getKey());

            if ($detalle->tanda_id === null || ! $detalle->isActive()) {
                throw ValidationException::withMessages([
                    'detalle' => 'Solo puedes anular productos que ya fueron enviados a cocina.',
                ]);
            }

            $detalle->update([
                'estado_linea' => EstadoLineaPedido::CANCELADA,
                'cancelada_por_id' => $actor->getKey(),
                'cancelada_at' => now(),
                'motivo_cancelacion' => $motivo,
            ]);

            $pedido = $detalle->pedido()->firstOrFail();
            $this->audit($pedido, $actor, 'detalle_pedido_anulado', [
                'detalle_id' => $detalle->getKey(),
                'motivo' => $motivo,
            ]);
        });
    }

    private function lockPedido(Pedido $pedido): Pedido
    {
        return Pedido::query()
            ->where('establecimiento_id', $this->establishmentId())
            ->lockForUpdate()
            ->findOrFail($pedido->getKey());
    }

    private function ensureEditable(Pedido $pedido): void
    {
        if ($pedido->estado_comercial !== EstadoComercialPedido::ABIERTO) {
            throw ValidationException::withMessages([
                'pedido' => 'Este pedido ya fue enviado a caja o cobrado y no acepta cambios.',
            ]);
        }
    }

    private function establishmentId(): int
    {
        $id = DB::table('establecimientos')->orderBy('id')->value('id');

        if (! $id) {
            throw ValidationException::withMessages([
                'establecimiento' => 'Configura un establecimiento antes de usar el Punto de Venta.',
            ]);
        }

        return (int) $id;
    }

    private function ensureActiveCashSession(int $establecimientoId): void
    {
        $sesion = SesionCaja::query()
            ->where('establecimiento_id', $establecimientoId)
            ->whereNull('fecha_cierre')
            ->latest('id')
            ->lockForUpdate()
            ->first();

        if (! $sesion) {
            throw ValidationException::withMessages([
                'sesion' => 'No hay una caja activa. Abre un turno antes de crear pedidos.',
            ]);
        }
    }

    private function nextCodigoCorto(int $establecimientoId): int
    {
        $fecha = now()->toDateString();

        $secuencia = DB::table('secuencias_pedidos')
            ->where('establecimiento_id', $establecimientoId)
            ->where('fecha', $fecha)
            ->lockForUpdate()
            ->first();

        if ($secuencia) {
            DB::table('secuencias_pedidos')
                ->where('id', $secuencia->id)
                ->increment('ultimo_valor');

            $secuencia = DB::table('secuencias_pedidos')->find($secuencia->id);
        } else {
            DB::table('secuencias_pedidos')->insert([
                'establecimiento_id' => $establecimientoId,
                'fecha' => $fecha,
                'ultimo_valor' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $secuencia = DB::table('secuencias_pedidos')
                ->where('establecimiento_id', $establecimientoId)
                ->where('fecha', $fecha)
                ->first();
        }

        return (int) $secuencia->ultimo_valor;
    }

    private function nextTrackingNumber(): string
    {
        do {
            $tracking = 'BW-'.now()->format('ymdHis').'-'.strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        } while (Pedido::query()->where('numero_seguimiento', $tracking)->exists());

        return $tracking;
    }

    private function normalizeComboSelection(Combo $combo, array $selection): array
    {
        $normalized = [];

        foreach ($combo->opcionesCombo->sortBy('id') as $option) {
            $rawItems = $selection[(string) $option->getKey()] ?? $selection[$option->getKey()] ?? [];
            $rawItems = is_array($rawItems) ? $rawItems : [];
            $allowedProducts = $option->productos->keyBy(fn (Producto $product): string => (string) $product->getKey());
            $items = [];
            $total = 0;

            foreach ($rawItems as $productId => $quantity) {
                $product = $allowedProducts->get((string) $productId);
                $quantity = (int) $quantity;

                if ($quantity < 1) {
                    continue;
                }

                if (! $product) {
                    throw ValidationException::withMessages([
                        'combo' => 'La selección contiene un producto que no pertenece al combo.',
                    ]);
                }

                if ($product->disponibilidad !== DisponibilidadProducto::DISPONIBLE) {
                    throw ValidationException::withMessages([
                        'combo' => "{$product->nombre} no está disponible para este combo.",
                    ]);
                }

                $items[] = [
                    'producto_id' => $product->getKey(),
                    'nombre' => $product->nombre,
                    'cantidad' => $quantity,
                ];
                $total += $quantity;
            }

            if ($option->es_obligatorio && $total !== (int) $option->cantidad_requerida) {
                throw ValidationException::withMessages([
                    'combo' => "El grupo {$option->nombre} debe tener exactamente {$option->cantidad_requerida} unidades.",
                ]);
            }

            if (! $option->es_obligatorio && $total > 0 && $total !== (int) $option->cantidad_requerida) {
                throw ValidationException::withMessages([
                    'combo' => "El grupo {$option->nombre} debe tener exactamente {$option->cantidad_requerida} unidades.",
                ]);
            }

            $normalized[] = [
                'opcion_combo_id' => $option->getKey(),
                'nombre' => $option->nombre,
                'cantidad_requerida' => (int) $option->cantidad_requerida,
                'items' => $items,
            ];
        }

        return $normalized;
    }

    private function sameSelection(?array $left, array $right): bool
    {
        return json_encode($left ?? [], JSON_UNESCAPED_UNICODE) === json_encode($right, JSON_UNESCAPED_UNICODE);
    }

    private function audit(Pedido $pedido, User $actor, string $type, array $payload = []): void
    {
        EventoAuditoria::create([
            'entidad_tipo' => Pedido::class,
            'entidad_id' => $pedido->getKey(),
            'usuario_id' => $actor->getKey(),
            'tipo_evento' => $type,
            'payload' => $payload,
        ]);
    }
}
