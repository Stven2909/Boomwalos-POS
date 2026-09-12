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
use App\Enums\MasaPupusa;
use App\Enums\OrigenPedido;
use App\Enums\TipoImpresora;
use App\Enums\TipoPedido;
use App\Enums\TipoTrabajoImpresion;
use App\Jobs\ProcessPrintJob;
use App\Models\Combo;
use App\Models\DetallePedido;
use App\Models\Establecimiento;
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
        private readonly PoliticaFlujosPos $flowPolicy,
    ) {}

    public function startOrder(TipoPedido $tipo, User $actor, ?int $mesaId = null, OrigenPedido $origen = OrigenPedido::CAJA): Pedido
    {
        return DB::transaction(function () use ($tipo, $actor, $mesaId, $origen): Pedido {
            $establecimientoId = $this->establishmentId();

            Establecimiento::query()->lockForUpdate()->findOrFail($establecimientoId);
            $this->flowPolicy->assertPuedeIniciar($tipo, true);

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
                    ->where(function ($query): void {
                        $query
                            ->whereIn('estado_comercial', [
                                EstadoComercialPedido::PENDIENTE_COBRO->value,
                                EstadoComercialPedido::COBRADO->value,
                            ])
                            ->orWhere(function ($query): void {
                                $query
                                    ->where('estado_comercial', EstadoComercialPedido::ABIERTO->value)
                                    ->whereHas('detalles', fn ($details) => $details->where('estado_linea', EstadoLineaPedido::ACTIVA->value));
                            });
                    })
                    ->latest('id')
                    ->first();

                if ($activeOrder) {
                    return $activeOrder->load(['mesa', 'detalles.producto', 'detalles.tanda']);
                }

                if ($mesa->estado !== EstadoMesa::LIBRE) {
                    $mesa->update(['estado' => EstadoMesa::LIBRE]);
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

    public function discardEmptyDraft(Pedido $pedido, User $actor, string $motivo = 'Borrador vacío abandonado'): bool
    {
        return DB::transaction(function () use ($pedido, $actor, $motivo): bool {
            $pedido = $this->lockPedido($pedido);

            if ($pedido->estado_comercial !== EstadoComercialPedido::ABIERTO) {
                return false;
            }

            $hasActiveLines = $pedido->detalles()
                ->where('estado_linea', EstadoLineaPedido::ACTIVA->value)
                ->exists();

            if ($hasActiveLines || $pedido->pago()->exists()) {
                return false;
            }

            $pedido->update(['estado_comercial' => EstadoComercialPedido::CANCELADO]);

            if ($pedido->mesa_id) {
                $pedido->mesa()->update(['estado' => EstadoMesa::LIBRE]);
            }

            $this->audit($pedido, $actor, 'borrador_vacio_cancelado', [
                'motivo' => $motivo,
                'codigo_corto' => $pedido->codigo_corto,
            ]);

            return true;
        });
    }

    public function discardEmptyDraftsForUser(User $actor): int
    {
        return $this->discardEmptyDrafts($actor, $actor->getKey());
    }

    public function discardEmptyDraftsForEstablishment(User $actor): int
    {
        return $this->discardEmptyDrafts($actor);
    }

    private function discardEmptyDrafts(User $actor, ?int $userId = null): int
    {
        return DB::transaction(function () use ($actor, $userId): int {
            $drafts = Pedido::query()
                ->where('establecimiento_id', $this->establishmentId())
                ->where('estado_comercial', EstadoComercialPedido::ABIERTO->value)
                ->when($userId !== null, fn ($query) => $query->where('usuario_id', $userId))
                ->whereDoesntHave('detalles', fn ($query) => $query->where('estado_linea', EstadoLineaPedido::ACTIVA->value))
                ->whereDoesntHave('pago')
                ->lockForUpdate()
                ->get();

            foreach ($drafts as $draft) {
                $draft->update(['estado_comercial' => EstadoComercialPedido::CANCELADO]);

                if ($draft->mesa_id) {
                    $draft->mesa()->update(['estado' => EstadoMesa::LIBRE]);
                }

                $this->audit($draft, $actor, 'borrador_vacio_cancelado', [
                    'motivo' => 'Borrador vacío limpiado al regresar al POS.',
                    'codigo_corto' => $draft->codigo_corto,
                ]);
            }

            return $drafts->count();
        });
    }

    public function addProduct(Pedido $pedido, Producto $producto, User $actor, ?string $masa = null): DetallePedido
    {
        return DB::transaction(function () use ($pedido, $producto, $actor, $masa): DetallePedido {
            $pedido = $this->lockPedido($pedido);
            $this->ensureEditable($pedido);

            $producto = Producto::query()->lockForUpdate()->findOrFail($producto->getKey());

            if ($producto->disponibilidad !== DisponibilidadProducto::DISPONIBLE) {
                throw ValidationException::withMessages([
                    'producto' => "{$producto->nombre} no está disponible para venta.",
                ]);
            }

            $configuracion = $this->productConfiguration($producto, $masa);
            $detalle = $pedido->detalles()
                ->whereNull('tanda_id')
                ->where('estado_linea', EstadoLineaPedido::ACTIVA->value)
                ->where('producto_id', $producto->getKey())
                ->whereNull('combo_id')
                ->lockForUpdate()
                ->get()
                ->first(fn (DetallePedido $line): bool => $this->sameConfiguration($line->configuracion_producto, $configuracion));

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
                    'configuracion_producto' => $configuracion,
                ]);
            }

            $this->audit($pedido, $actor, 'producto_agregado', [
                'producto_id' => $producto->getKey(),
                'detalle_id' => $detalle->getKey(),
                'configuracion_producto' => $configuracion,
            ]);

            return $detalle->fresh(['producto', 'tanda']);
        });
    }

    public function addCombo(Pedido $pedido, Combo $combo, array $selection, User $actor, ?string $masa = null): DetallePedido
    {
        return DB::transaction(function () use ($pedido, $combo, $selection, $actor, $masa): DetallePedido {
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
            $configuracion = $this->comboConfiguration($combo, $normalized, $masa);
            $sameLine = $pedido->detalles()
                ->whereNull('tanda_id')
                ->where('estado_linea', EstadoLineaPedido::ACTIVA->value)
                ->where('combo_id', $combo->getKey())
                ->whereNull('producto_id')
                ->lockForUpdate()
                ->get()
                ->first(fn (DetallePedido $line): bool => $this->comboSelectionValidator->same($line->seleccion_combo, $normalized)
                    && $this->sameConfiguration($line->configuracion_producto, $configuracion));

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
                    'configuracion_producto' => $configuracion,
                ])->load(['combo', 'tanda']);
            }

            $this->audit($pedido, $actor, 'combo_agregado', [
                'combo_id' => $combo->getKey(),
                'detalle_id' => $detail->getKey(),
                'seleccion_combo' => $normalized,
                'configuracion_producto' => $configuracion,
            ]);

            return $detail;
        });
    }

    public function updatePendingCombo(Pedido $pedido, DetallePedido $detail, array $selection, User $actor, ?string $masa = null): DetallePedido
    {
        return DB::transaction(function () use ($pedido, $detail, $selection, $actor, $masa): DetallePedido {
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
            $configuracion = $this->comboConfiguration($combo, $normalized, $masa);

            $otherLine = $pedido->detalles()
                ->whereNull('tanda_id')
                ->where('estado_linea', EstadoLineaPedido::ACTIVA->value)
                ->where('combo_id', $combo->getKey())
                ->whereNull('producto_id')
                ->where('id', '<>', $detail->getKey())
                ->lockForUpdate()
                ->get()
                ->first(fn (DetallePedido $line): bool => $this->comboSelectionValidator->same($line->seleccion_combo, $normalized)
                    && $this->sameConfiguration($line->configuracion_producto, $configuracion));

            if ($otherLine) {
                $otherLine->increment('cantidad', $detail->cantidad);
                $detail->delete();
                $updated = $otherLine->fresh(['combo', 'tanda']);
            } else {
                $detail->update([
                    'seleccion_combo' => $normalized,
                    'configuracion_producto' => $configuracion,
                ]);
                $updated = $detail->fresh(['combo', 'tanda']);
            }

            $this->audit($pedido, $actor, 'combo_editado', [
                'combo_id' => $combo->getKey(),
                'detalle_id' => $updated->getKey(),
                'seleccion_combo' => $normalized,
                'configuracion_producto' => $configuracion,
            ]);

            return $updated;
        });
    }

    public function restorePendingCombo(Pedido $pedido, int $comboId, int $quantity, string $price, array $selection, ?string $masa = null): DetallePedido
    {
        return DB::transaction(function () use ($pedido, $comboId, $quantity, $price, $selection, $masa): DetallePedido {
            $pedido = $this->lockPedido($pedido);
            $this->ensureEditable($pedido);
            $combo = Combo::query()->with('opcionesCombo.productos')->findOrFail($comboId);
            $normalized = $this->comboSelectionValidator->normalize($combo, $selection);
            $configuracion = $this->comboConfiguration($combo, $normalized, $masa);

            $sameLine = $pedido->detalles()
                ->whereNull('tanda_id')
                ->where('estado_linea', EstadoLineaPedido::ACTIVA->value)
                ->where('combo_id', $comboId)
                ->whereNull('producto_id')
                ->lockForUpdate()
                ->get()
                ->first(fn (DetallePedido $line): bool => $this->comboSelectionValidator->same($line->seleccion_combo, $normalized)
                    && $this->sameConfiguration($line->configuracion_producto, $configuracion));

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
                'configuracion_producto' => $configuracion,
            ])->load('combo');
        });
    }

    public function updatePendingProduct(Pedido $pedido, DetallePedido $detail, string $masa, User $actor): DetallePedido
    {
        return DB::transaction(function () use ($pedido, $detail, $masa): DetallePedido {
            $pedido = $this->lockPedido($pedido);
            $this->ensureEditable($pedido);

            $detail = $pedido->detalles()
                ->whereKey($detail->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $detail->isPending() || ! $detail->producto_id) {
                throw ValidationException::withMessages([
                    'producto' => 'Solo puedes editar productos pendientes.',
                ]);
            }

            $producto = Producto::query()->lockForUpdate()->findOrFail($detail->producto_id);
            $configuracion = $this->productConfiguration($producto, $masa);

            $otherLine = $pedido->detalles()
                ->whereNull('tanda_id')
                ->where('estado_linea', EstadoLineaPedido::ACTIVA->value)
                ->where('producto_id', $producto->getKey())
                ->whereNull('combo_id')
                ->where('id', '<>', $detail->getKey())
                ->lockForUpdate()
                ->get()
                ->first(fn (DetallePedido $line): bool => $this->sameConfiguration($line->configuracion_producto, $configuracion));

            if ($otherLine) {
                $otherLine->increment('cantidad', $detail->cantidad);
                $detail->delete();

                return $otherLine->fresh(['producto', 'tanda']);
            }

            $detail->update(['configuracion_producto' => $configuracion]);

            return $detail->fresh(['producto', 'tanda']);
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

    public function restorePendingLine(Pedido $pedido, int $productoId, int $cantidad, string $precioUnitario, ?string $masa = null): DetallePedido
    {
        return DB::transaction(function () use ($pedido, $productoId, $cantidad, $precioUnitario, $masa): DetallePedido {
            $pedido = $this->lockPedido($pedido);
            $this->ensureEditable($pedido);
            $producto = Producto::query()->findOrFail($productoId);
            $configuracion = $this->productConfiguration($producto, $masa);

            $detalle = $pedido->detalles()
                ->whereNull('tanda_id')
                ->where('estado_linea', EstadoLineaPedido::ACTIVA->value)
                ->where('producto_id', $productoId)
                ->whereNull('combo_id')
                ->lockForUpdate()
                ->get()
                ->first(fn (DetallePedido $line): bool => $this->sameConfiguration($line->configuracion_producto, $configuracion));

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
                    'configuracion_producto' => $configuracion,
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

            $printer = Impresora::buscar(TipoImpresora::COMANDA, $pedido->establecimiento_id);
            $contenido = $this->comandaRenderer->render($pedido, $pendingLines);
            $lineIds = $pendingLines->pluck('id')->map(fn ($id): int => (int) $id)->all();
            sort($lineIds);
            $uid = hash('sha256', $pedido->getKey().'|COMANDA|'.implode(',', $lineIds));

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

            if ($pedido->tipo_pedido !== TipoPedido::MESA) {
                throw ValidationException::withMessages([
                    'pedido' => 'Solo las cuentas de mesa se envían a caja para cobrar después.',
                ]);
            }

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

            if ($activeLines->contains(fn (DetallePedido $line): bool => $line->tanda_id === null)) {
                throw ValidationException::withMessages([
                    'pedido' => 'Envía primero todos los productos pendientes a cocina.',
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
            $establishmentId = $this->establishmentId();
            Establecimiento::query()->lockForUpdate()->findOrFail($establishmentId);
            $this->flowPolicy->assertPuedeIniciar(TipoPedido::MESA, true);
            $pedido = $this->lockPedido($pedido);
            $this->ensureEditable($pedido);

            $mesa = Mesa::query()
                ->where('establecimiento_id', $establishmentId)
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

    private function productConfiguration(Producto $producto, ?string $masa): ?array
    {
        if (! $producto->requiere_masa) {
            if ($masa !== null && trim($masa) !== '') {
                throw ValidationException::withMessages([
                    'masa' => 'Este producto no requiere selección de masa.',
                ]);
            }

            return null;
        }

        return $this->masaConfiguration($masa);
    }

    private function comboConfiguration(Combo $combo, array $selection, ?string $masa): ?array
    {
        $allowedProducts = $combo->opcionesCombo
            ->flatMap(fn ($option) => $option->productos)
            ->keyBy(fn (Producto $product): string => (string) $product->getKey());

        $massItems = collect($selection)
            ->flatMap(fn (array $group): array => $group['items'] ?? [])
            ->filter(fn (array $item): bool => (bool) $allowedProducts->get((string) ($item['producto_id'] ?? ''))?->requiere_masa);

        if ($massItems->isEmpty()) {
            if ($masa !== null && trim($masa) !== '') {
                throw ValidationException::withMessages([
                    'masa' => 'Este combo no requiere selección de masa.',
                ]);
            }

            return null;
        }

        $missingItemMass = $massItems->contains(fn (array $item): bool => ! data_get($item, 'masa.codigo'));

        if (! $missingItemMass) {
            if ($masa !== null && trim($masa) !== '') {
                throw ValidationException::withMessages([
                    'masa' => 'La masa se define por cada pupusa del combo.',
                ]);
            }

            return null;
        }

        // Compatibilidad con combos antiguos que todavía llegan con una masa global.
        return $this->masaConfiguration($masa);
    }

    private function masaConfiguration(?string $masa): array
    {
        $selected = MasaPupusa::tryFrom(strtoupper(trim((string) $masa)));

        if (! $selected) {
            throw ValidationException::withMessages([
                'masa' => 'Selecciona si la preparación será de maíz o de arroz.',
            ]);
        }

        return [
            'masa' => [
                'codigo' => $selected->value,
                'nombre' => $selected->label(),
            ],
        ];
    }

    private function sameConfiguration(?array $left, ?array $right): bool
    {
        return json_encode($left ?? [], JSON_UNESCAPED_UNICODE) === json_encode($right ?? [], JSON_UNESCAPED_UNICODE);
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
