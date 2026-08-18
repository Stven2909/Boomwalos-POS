<?php

namespace App\Services;

use App\Application\Printing\RenderKitchenComanda;
use App\Contracts\AuditLoggerInterface;
use App\Contracts\EstablishmentContextInterface;
use App\Enums\DisponibilidadProducto;
use App\Enums\EstadoComercialPedido;
use App\Enums\EstadoImpresion;
use App\Enums\EstadoLineaPedido;
use App\Enums\EstadoMesa;
use App\Enums\OrigenPedido;
use App\Enums\TipoImpresora;
use App\Enums\TipoPedido;
use App\Enums\TipoTrabajoImpresion;
use App\Jobs\ProcessPrintJob;
use App\Models\Combo;
use App\Models\DetallePedido;
use App\Models\Impresora;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\SesionCaja;
use App\Models\TrabajoImpresion;
use App\Models\User;
use App\Services\Orders\ComboSelectionValidator;
use App\Services\Orders\PedidoNumberService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PedidoService
{
    public function __construct(
        private readonly EstablishmentContextInterface $establishmentContext,
        private readonly AuditLoggerInterface $auditLogger,
        private readonly ComboSelectionValidator $comboSelectionValidator,
        private readonly PedidoNumberService $pedidoNumberService,
        private readonly RenderKitchenComanda $comandaRenderer,
    ) {}

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

            $codigoCorto = $this->pedidoNumberService->nextShortCode($establecimientoId);

            $pedido = Pedido::create([
                'numero_seguimiento' => $this->pedidoNumberService->nextTracking(),
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

            $normalized = $this->comboSelectionValidator->normalize($combo, $selection);
            $sameLine = $pedido->detalles()
                ->whereNull('tanda_id')
                ->where('estado_linea', EstadoLineaPedido::ACTIVA->value)
                ->where('combo_id', $combo->getKey())
                ->whereNull('producto_id')
                ->lockForUpdate()
                ->get()
                ->first(fn (DetallePedido $line): bool => $this->comboSelectionValidator->same($line->seleccion_combo, $normalized));

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
            $normalized = $this->comboSelectionValidator->normalize($combo, $selection);

            $otherLine = $pedido->detalles()
                ->whereNull('tanda_id')
                ->where('estado_linea', EstadoLineaPedido::ACTIVA->value)
                ->where('combo_id', $combo->getKey())
                ->whereNull('producto_id')
                ->where('id', '<>', $detail->getKey())
                ->lockForUpdate()
                ->get()
                ->first(fn (DetallePedido $line): bool => $this->comboSelectionValidator->same($line->seleccion_combo, $normalized));

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
            $normalized = $this->comboSelectionValidator->normalize($combo, $selection);

            $sameLine = $pedido->detalles()
                ->whereNull('tanda_id')
                ->where('estado_linea', EstadoLineaPedido::ACTIVA->value)
                ->where('combo_id', $comboId)
                ->whereNull('producto_id')
                ->lockForUpdate()
                ->get()
                ->first(fn (DetallePedido $line): bool => $this->comboSelectionValidator->same($line->seleccion_combo, $normalized));

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

    public function sendPendingBatch(Pedido $pedido, User $actor): TrabajoImpresion
    {
        return DB::transaction(function () use ($pedido, $actor): TrabajoImpresion {
            $pedido = $this->lockPedido($pedido);
            $this->ensureEditable($pedido);

            $pendingLines = $pedido->detalles()
                ->whereNull('tanda_id')
                ->where('estado_linea', EstadoLineaPedido::ACTIVA->value)
                ->lockForUpdate()
                ->get();

            if ($pendingLines->isEmpty()) {
                throw ValidationException::withMessages([
                    'pedido' => 'Agrega al menos un producto nuevo antes de enviar a cocina.',
                ]);
            }

            $printer = Impresora::buscar(TipoImpresora::COMANDA);
            $contenido = $this->comandaRenderer->render($pedido);
            $uid = hash('sha256', $pedido->getKey() . '|COMANDA|' . now()->timestamp . '|' . uniqid('', true));

            $job = TrabajoImpresion::create([
                'impresora_id' => $printer?->getKey(),
                'pedido_id' => $pedido->getKey(),
                'tipo_trabajo' => TipoTrabajoImpresion::COMANDA,
                'estado' => $printer ? EstadoImpresion::PENDIENTE : EstadoImpresion::ERROR,
                'contenido' => $contenido,
                'original_uid' => $uid,
                'ultimo_error' => $printer ? null : 'No hay impresora de comanda configurada.',
            ]);

            $pendingLines->each(fn (DetallePedido $detalle): bool => $detalle->update(['tanda_id' => $job->getKey()]));

            $this->audit($pedido, $actor, $printer ? 'comanda_en_cola' : 'comanda_sin_impresora', [
                'trabajo_impresion_id' => $job->getKey(),
            ]);

            $this->audit($pedido, $actor, 'pedido_enviado_cocina', [
                'trabajo_impresion_id' => $job->getKey(),
                'detalles' => $pendingLines->map(fn (DetallePedido $detalle): array => [
                    'id' => $detalle->getKey(),
                    'producto_id' => $detalle->producto_id,
                    'cantidad' => $detalle->cantidad,
                ])->values()->all(),
            ]);

            if ($printer) {
                ProcessPrintJob::dispatch($job->getKey())->afterCommit();
            }

            return $job;
        });
    }

    public function sendToCashRegister(Pedido $pedido, User $actor): Pedido
    {
        return DB::transaction(function () use ($pedido, $actor): Pedido {
            $pedido = $this->lockPedido($pedido);

            if ($pedido->estado_comercial !== EstadoComercialPedido::ABIERTO) {
                throw ValidationException::withMessages([
                    'pedido' => 'Este pedido ya fue enviado a caja.',
                ]);
            }

            $activeLines = $pedido->detalles()
                ->where('estado_linea', EstadoLineaPedido::ACTIVA->value)
                ->lockForUpdate()
                ->get();

            if ($activeLines->isEmpty()) {
                throw ValidationException::withMessages([
                    'pedido' => 'Agrega al menos un producto antes de enviar la cuenta a caja.',
                ]);
            }

            $pedido->update(['estado_comercial' => EstadoComercialPedido::PENDIENTE_COBRO]);

            $this->audit($pedido, $actor, 'pedido_enviado_caja', [
                'origen_pedido' => $pedido->origen_pedido?->value,
                'codigo_corto' => $pedido->codigo_corto,
                'total_lineas' => $activeLines->count(),
            ]);

            return $pedido->fresh(['mesa', 'detalles.producto', 'detalles.tanda']);
        });
    }

    public function assignTable(Pedido $pedido, Mesa $mesa, User $actor): Pedido
    {
        return DB::transaction(function () use ($pedido, $mesa, $actor): Pedido {
            $pedido = $this->lockPedido($pedido);
            $this->ensureEditable($pedido);

            $mesa = Mesa::query()
                ->where('establecimiento_id', $this->establishmentId())
                ->lockForUpdate()
                ->findOrFail($mesa->getKey());

            if ($pedido->mesa_id === $mesa->getKey()) {
                return $pedido->fresh(['mesa', 'detalles.producto', 'detalles.tanda']);
            }

            $activeStates = [
                EstadoComercialPedido::ABIERTO->value,
                EstadoComercialPedido::PENDIENTE_COBRO->value,
                EstadoComercialPedido::COBRADO->value,
            ];

            $otherOrder = $mesa->pedidos()
                ->whereIn('estado_comercial', $activeStates)
                ->where('id', '<>', $pedido->getKey())
                ->exists();

            if ($otherOrder) {
                throw ValidationException::withMessages([
                    'mesa' => "La mesa {$mesa->numero} ya tiene una cuenta abierta.",
                ]);
            }

            if (! in_array($mesa->estado, [EstadoMesa::LIBRE, EstadoMesa::OCUPADA], true)) {
                throw ValidationException::withMessages([
                    'mesa' => 'La mesa no está disponible en este momento.',
                ]);
            }

            $previousMesaId = $pedido->mesa_id;

            if ($previousMesaId && $previousMesaId !== $mesa->getKey()) {
                $previousMesa = Mesa::query()->find($previousMesaId);

                $stillUsed = $previousMesa
                    ? $previousMesa->pedidos()
                        ->whereIn('estado_comercial', $activeStates)
                        ->where('id', '<>', $pedido->getKey())
                        ->exists()
                    : false;

                if ($previousMesa && ! $stillUsed) {
                    $previousMesa->update(['estado' => EstadoMesa::LIBRE]);
                }
            }

            $pedido->update([
                'mesa_id' => $mesa->getKey(),
                'tipo_pedido' => TipoPedido::MESA,
            ]);

            $mesa->update(['estado' => EstadoMesa::OCUPADA]);

            $this->audit($pedido, $actor, 'mesa_asignada', [
                'mesa_id' => $mesa->getKey(),
                'mesa_anterior_id' => $previousMesaId,
            ]);

            return $pedido->fresh(['mesa', 'detalles.producto', 'detalles.tanda']);
        });
    }

    public function cancelOrder(Pedido $pedido, User $actor, string $motivo = 'Anulación del pedido'): Pedido
    {
        if (! $actor->can('cancelar_pedido')) {
            throw new AuthorizationException('No tienes permiso para cancelar pedidos.');
        }

        return DB::transaction(function () use ($pedido, $actor, $motivo): Pedido {
            $pedido = $this->lockPedido($pedido);

            if (! $pedido->estado_comercial->isPayable()) {
                throw ValidationException::withMessages([
                    'pedido' => 'Solo puedes cancelar pedidos que todavía no fueron cobrados.',
                ]);
            }

            $pedido->update(['estado_comercial' => EstadoComercialPedido::CANCELADO]);

            if ($pedido->mesa_id) {
                $pedido->mesa()->update(['estado' => EstadoMesa::LIBRE]);
            }

            $this->audit($pedido, $actor, 'pedido_cancelado', [
                'motivo' => $motivo,
                'origen_pedido' => $pedido->origen_pedido?->value,
                'codigo_corto' => $pedido->codigo_corto,
            ]);

            return $pedido->fresh(['mesa']);
        });
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
        return $this->establishmentContext->id();
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

    private function audit(Pedido $pedido, User $actor, string $type, array $payload = []): void
    {
        $this->auditLogger->record($pedido, $actor, $type, $payload);
    }
}
