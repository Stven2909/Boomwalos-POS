<?php

namespace App\Filament\Pages\Pos;

use App\Enums\DisponibilidadProducto;
use App\Enums\EstadoComercialPedido;
use App\Enums\EstadoLineaPedido;
use App\Enums\OrigenPedido;
use App\Models\Categoria;
use App\Models\Combo;
use App\Models\DetallePedido;
use App\Models\Pedido;
use App\Models\Producto;
use App\Services\PedidoService;
use Illuminate\Validation\ValidationException;

class OrderEntry extends PosPage
{
    private const RESERVED_COMBO_CATEGORY_NAME = 'combos';

    protected static ?string $slug = 'pos/orden/{pedido}';

    protected static ?string $title = 'Tomar orden';

    protected string $view = 'filament.admin.pages.pos.order-entry';

    public Pedido $pedido;

    public string $search = '';

    public string $category = 'all';

    public ?array $undoLine = null;

    public ?string $feedback = null;

    public bool $comboModalOpen = false;

    public ?int $selectedComboId = null;

    public ?int $editingComboLineId = null;

    public array $comboSelections = [];

    public function mount(Pedido $pedido): void
    {
        if (! $this->ensureCashSession()) {
            return;
        }

        abort_unless($pedido->establecimiento_id === $this->establishment()->getKey(), 404);
        abort_unless($pedido->isOpen(), 404);

        $this->pedido = $pedido;
        $this->refreshPedido();
    }

    public function selectCategory(string $category): void
    {
        $this->category = $category;
    }

    public function openCombo(int $comboId): void
    {
        $combo = $this->availableCombos()->firstWhere('id', $comboId);

        if (! $combo) {
            $this->feedback = 'Este combo ya no está disponible.';

            return;
        }

        $this->selectedComboId = $combo->getKey();
        $this->editingComboLineId = null;
        $this->comboSelections = $this->emptyComboSelection($combo);
        $this->comboModalOpen = true;
    }

    public function editCombo(int $lineId): void
    {
        $line = $this->pendingLine($lineId);

        if (! $line?->combo_id) {
            return;
        }

        $combo = $this->availableCombos()->firstWhere('id', $line->combo_id);

        if (! $combo) {
            $this->feedback = 'Este combo ya no está disponible para editar.';

            return;
        }

        $this->selectedComboId = $combo->getKey();
        $this->editingComboLineId = $line->getKey();
        $this->comboSelections = $this->selectionState($combo, $line->seleccion_combo ?? []);
        $this->comboModalOpen = true;
    }

    public function closeCombo(): void
    {
        $this->comboModalOpen = false;
        $this->selectedComboId = null;
        $this->editingComboLineId = null;
        $this->comboSelections = [];
    }

    public function changeComboSelection(int $optionId, int $productId, int $delta): void
    {
        $combo = $this->selectedCombo;
        $option = $combo?->opcionesCombo->firstWhere('id', $optionId);

        if (! $combo || ! $option || ! $option->productos->contains('id', $productId)) {
            return;
        }

        $current = (int) ($this->comboSelections[(string) $optionId][(string) $productId] ?? 0);
        $next = max(0, $current + $delta);
        $total = $this->comboSelectionTotal($optionId) - $current + $next;

        if ($delta > 0 && $total > (int) $option->cantidad_requerida) {
            return;
        }

        $this->comboSelections[(string) $optionId][(string) $productId] = $next;
    }

    public function saveComboSelection(): void
    {
        $combo = $this->selectedCombo;

        if (! $combo || ! $this->comboReady()) {
            $this->feedback = 'Completa las cantidades requeridas del combo antes de agregarlo.';

            return;
        }

        try {
            if ($this->editingComboLineId) {
                $line = $this->pendingLine($this->editingComboLineId);

                if ($line) {
                    app(PedidoService::class)->updatePendingCombo($this->pedido, $line, $this->comboSelections, auth()->user());
                }
            } else {
                app(PedidoService::class)->addCombo($this->pedido, $combo, $this->comboSelections, auth()->user());
            }

            $this->feedback = null;
            $this->closeCombo();
            $this->refreshPedido();
        } catch (ValidationException $exception) {
            $this->feedback = collect($exception->errors())->flatten()->first() ?? 'No se pudo guardar el combo.';
        }
    }

    public function addProduct(int $productoId): void
    {
        try {
            $producto = Producto::query()->findOrFail($productoId);
            app(PedidoService::class)->addProduct($this->pedido, $producto, auth()->user());
            $this->feedback = null;
            $this->refreshPedido();
        } catch (ValidationException $exception) {
            $this->feedback = collect($exception->errors())->flatten()->first() ?? 'No se pudo agregar el producto.';
        }
    }

    public function incrementLine(int $lineId): void
    {
        $line = $this->pendingLine($lineId);

        if (! $line) {
            return;
        }

        try {
            app(PedidoService::class)->updatePendingQuantity($this->pedido, $line, $line->cantidad + 1);
            $this->refreshPedido();
        } catch (ValidationException $exception) {
            $this->feedback = collect($exception->errors())->flatten()->first();
        }
    }

    public function decrementLine(int $lineId): void
    {
        $line = $this->pendingLine($lineId);

        if (! $line) {
            return;
        }

        if ($line->cantidad <= 1) {
            $this->removeLine($lineId);

            return;
        }

        try {
            app(PedidoService::class)->updatePendingQuantity($this->pedido, $line, $line->cantidad - 1);
            $this->refreshPedido();
        } catch (ValidationException $exception) {
            $this->feedback = collect($exception->errors())->flatten()->first();
        }
    }

    public function removeLine(int $lineId): void
    {
        $line = $this->pendingLine($lineId);

        if (! $line) {
            return;
        }

        $this->undoLine = $line->combo_id
            ? [
                'kind' => 'combo',
                'combo_id' => (int) $line->combo_id,
                'cantidad' => (int) $line->cantidad,
                'precio_unitario' => (string) $line->precio_unitario,
                'seleccion_combo' => $line->seleccion_combo ?? [],
                'nombre' => $line->combo?->nombre ?? 'Combo',
            ]
            : [
                'kind' => 'producto',
                'producto_id' => (int) $line->producto_id,
                'cantidad' => (int) $line->cantidad,
                'precio_unitario' => (string) $line->precio_unitario,
                'nombre' => $line->producto?->nombre ?? 'Producto',
            ];

        try {
            app(PedidoService::class)->removePendingLine($this->pedido, $line);
            $this->feedback = null;
            $this->refreshPedido();
        } catch (ValidationException $exception) {
            $this->feedback = collect($exception->errors())->flatten()->first();
        }
    }

    public function undoRemove(): void
    {
        if (! $this->undoLine) {
            return;
        }

        try {
            if (($this->undoLine['kind'] ?? 'producto') === 'combo') {
                app(PedidoService::class)->restorePendingCombo(
                    $this->pedido,
                    $this->undoLine['combo_id'],
                    $this->undoLine['cantidad'],
                    $this->undoLine['precio_unitario'],
                    $this->selectionStateForService($this->undoLine['seleccion_combo']),
                );
            } else {
                app(PedidoService::class)->restorePendingLine(
                    $this->pedido,
                    $this->undoLine['producto_id'],
                    $this->undoLine['cantidad'],
                    $this->undoLine['precio_unitario'],
                );
            }
            $this->undoLine = null;
            $this->refreshPedido();
        } catch (ValidationException $exception) {
            $this->feedback = collect($exception->errors())->flatten()->first();
        }
    }

    public function sendToKitchen(): void
    {
        try {
            $tanda = app(PedidoService::class)->sendPendingBatch($this->pedido, auth()->user());
            $printMessage = $tanda->trabajosImpresion()->exists()
                ? ' Comanda en cola de impresión.'
                : ' No hay una impresora de comanda configurada.';
            session()->flash('pos_feedback', "Tanda {$tanda->numero_tanda} enviada a cocina.{$printMessage} La cuenta sigue abierta.");
            $this->undoLine = null;
            $this->redirect(ServiceSelection::getUrl());
        } catch (ValidationException $exception) {
            $this->feedback = collect($exception->errors())->flatten()->first() ?? 'No se pudo enviar el pedido.';
        }
    }

    public function sendToCashRegister(): void
    {
        try {
            app(PedidoService::class)->sendToCashRegister($this->pedido, auth()->user());
            session()->flash('pos_feedback', 'La cuenta fue enviada a caja. Espera el cobro en el mostrador.');
            $this->undoLine = null;
            $this->redirect(ServiceSelection::getUrl());
        } catch (ValidationException $exception) {
            $this->feedback = collect($exception->errors())->flatten()->first() ?? 'No se pudo enviar el pedido a caja.';
        }
    }

    public function openCharge(): void
    {
        if (! $this->canCharge) {
            $this->feedback = $this->pendingDetails->isNotEmpty()
                ? 'Envía a cocina los productos pendientes antes de cobrar.'
                : 'La cuenta todavía no tiene productos enviados a cocina.';

            return;
        }

        $this->redirect(ChargeOrder::getUrl(['pedido' => $this->pedido->getKey()]));
    }

    public function cancelSentLine(int $lineId): void
    {
        $line = $this->pedido->detalles->firstWhere('id', $lineId);

        if (! $line) {
            return;
        }

        try {
            app(PedidoService::class)->cancelSentLine($line, auth()->user());
            $this->feedback = 'Producto anulado y registrado en auditoría.';
            $this->refreshPedido();
        } catch (\Throwable $exception) {
            $this->feedback = $exception->getMessage();
        }
    }

    public function getCategoriesProperty()
    {
        return Categoria::query()
            ->where('activa', true)
            ->whereRaw('LOWER(TRIM(nombre)) <> ?', [self::RESERVED_COMBO_CATEGORY_NAME])
            ->orderBy('nombre')
            ->get();
    }

    public function getProductsProperty()
    {
        if ($this->category === 'combos') {
            return collect();
        }

        return Producto::query()
            ->with('categoria')
            ->where('disponibilidad', DisponibilidadProducto::DISPONIBLE->value)
            ->whereHas('categoria', fn ($query) => $query->where('activa', true))
            ->when($this->category !== 'all', fn ($query) => $query->where('categoria_id', $this->category))
            ->when(trim($this->search) !== '', fn ($query) => $query->where('nombre', 'like', '%' . trim($this->search) . '%'))
            ->orderBy('nombre')
            ->get();
    }

    public function getCombosProperty()
    {
        if ($this->category !== 'all' && $this->category !== 'combos') {
            return collect();
        }

        return $this->availableCombos();
    }

    public function getSelectedComboProperty(): ?Combo
    {
        if (! $this->selectedComboId) {
            return null;
        }

        return $this->availableCombos()->firstWhere('id', $this->selectedComboId);
    }

    public function getPendingDetailsProperty()
    {
        return $this->pedido->detalles
            ->whereNull('tanda_id')
            ->where('estado_linea', EstadoLineaPedido::ACTIVA)
            ->values();
    }

    public function getSentDetailsProperty()
    {
        return $this->pedido->detalles
            ->whereNotNull('tanda_id')
            ->values();
    }

    public function getTotalProperty(): float
    {
        return $this->pedido->total();
    }

    public function getCanChargeProperty(): bool
    {
        return $this->pedido->estado_comercial === EstadoComercialPedido::ABIERTO
            && $this->pendingDetails->isEmpty()
            && $this->sentDetails->where('estado_linea', EstadoLineaPedido::ACTIVA)->isNotEmpty()
            && auth()->user()?->can('cobrar_pedido');
    }

    public function getIsDeviceOrderProperty(): bool
    {
        return $this->pedido->origen_pedido === OrigenPedido::DISPOSITIVO;
    }

    public function getIsReadOnlyProperty(): bool
    {
        return ! $this->pedido->estado_comercial->isPayable();
    }

    public function comboSelectionTotal(int $optionId): int
    {
        return collect($this->comboSelections[(string) $optionId] ?? [])->sum(fn ($quantity): int => (int) $quantity);
    }

    public function comboReady(): bool
    {
        $combo = $this->selectedCombo;

        if (! $combo) {
            return false;
        }

        return $combo->opcionesCombo->every(function ($option): bool {
            $total = $this->comboSelectionTotal($option->getKey());

            return $option->es_obligatorio
                ? $total === (int) $option->cantidad_requerida
                : ($total === 0 || $total === (int) $option->cantidad_requerida);
        });
    }

    public function comboLineSummary(DetallePedido $line): string
    {
        return collect($line->seleccion_combo ?? [])
            ->flatMap(fn (array $group): array => collect($group['items'] ?? [])->map(fn (array $item): string => $item['cantidad'] . ' ' . $item['nombre'])->all())
            ->implode(', ');
    }

    private function pendingLine(int $lineId): ?DetallePedido
    {
        return $this->pedido->detalles
            ->first(fn (DetallePedido $line): bool => $line->getKey() === $lineId && $line->isPending());
    }

    private function refreshPedido(): void
    {
        $this->pedido->refresh()->load([
            'mesa',
            'detalles.producto',
            'detalles.combo',
            'detalles.tanda',
        ]);
    }

    private function availableCombos()
    {
        return Combo::query()
            ->with('opcionesCombo.productos')
            ->where('disponibilidad', DisponibilidadProducto::DISPONIBLE->value)
            ->when(trim($this->search) !== '', fn ($query) => $query->where('nombre', 'like', '%' . trim($this->search) . '%'))
            ->orderBy('nombre')
            ->get();
    }

    private function emptyComboSelection(Combo $combo): array
    {
        return $combo->opcionesCombo->mapWithKeys(fn ($option): array => [
            (string) $option->getKey() => $option->productos->mapWithKeys(fn (Producto $product): array => [(string) $product->getKey() => 0])->all(),
        ])->all();
    }

    private function selectionState(Combo $combo, array $storedSelection): array
    {
        $state = $this->emptyComboSelection($combo);

        foreach ($storedSelection as $group) {
            $optionId = (string) ($group['opcion_combo_id'] ?? '');

            foreach ($group['items'] ?? [] as $item) {
                $productId = (string) ($item['producto_id'] ?? '');

                if (isset($state[$optionId][$productId])) {
                    $state[$optionId][$productId] = (int) ($item['cantidad'] ?? 0);
                }
            }
        }

        return $state;
    }

    private function selectionStateForService(array $storedSelection): array
    {
        return collect($storedSelection)->mapWithKeys(function (array $group): array {
            return [
                (string) ($group['opcion_combo_id'] ?? '') => collect($group['items'] ?? [])
                    ->mapWithKeys(fn (array $item): array => [(string) ($item['producto_id'] ?? '') => (int) ($item['cantidad'] ?? 0)])
                    ->all(),
            ];
        })->all();
    }
}
