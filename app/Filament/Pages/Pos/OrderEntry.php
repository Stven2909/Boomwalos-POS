<?php

namespace App\Filament\Pages\Pos;

use App\Enums\DisponibilidadProducto;
use App\Enums\EstadoComercialPedido;
use App\Enums\EstadoLineaPedido;
use App\Enums\EstadoMesa;
use App\Enums\OrigenPedido;
use App\Enums\ZonaMesa;
use App\Models\Categoria;
use App\Models\Combo;
use App\Models\DetallePedido;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Services\PedidoService;
use Illuminate\Support\Facades\DB;
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

    public ?string $selectedGroup = null;

    public ?array $undoLine = null;

    public ?string $feedback = null;

    public bool $comboModalOpen = false;

    public ?int $selectedComboId = null;

    public ?int $editingComboLineId = null;

    public array $comboSelections = [];

    public bool $mesaModalOpen = false;

    public string $mesaZona = ZonaMesa::SALON->value;

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

    public function selectGroup(?string $groupId): void
    {
        $this->selectedGroup = $groupId;
        $this->category = 'all';
    }

    public function backToGroups(): void
    {
        $this->selectedGroup = null;
        $this->category = 'all';
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
            $this->feedback = 'Agrega al menos un producto antes de cobrar y enviar a cocina.';

            return;
        }

        $this->redirect(ChargeOrder::getUrl(['pedido' => $this->pedido->getKey()]));
    }

    public function openMesaPicker(): void
    {
        $this->mesaZona = $this->pedido->mesa?->zona?->value ?? ZonaMesa::SALON->value;
        $this->mesaModalOpen = true;
    }

    public function closeMesaPicker(): void
    {
        $this->mesaModalOpen = false;
    }

    public function setMesaZona(string $zona): void
    {
        $this->mesaZona = (ZonaMesa::tryFrom($zona) ?? ZonaMesa::SALON)->value;
    }

    public function assignTable(int $mesaId): void
    {
        $mesa = $this->mesas->firstWhere('id', $mesaId);

        if (! $mesa) {
            return;
        }

        $wasAssigned = $this->pedido->mesa_id === $mesa->getKey();

        try {
            app(PedidoService::class)->assignTable($this->pedido, $mesa, auth()->user());
            $this->feedback = $wasAssigned
                ? 'La mesa ya estaba asignada a este pedido.'
                : 'Mesa ' . $mesa->numero . ' asignada. El pedido pasó a "en el local".';
            $this->mesaModalOpen = false;
            $this->refreshPedido();
        } catch (ValidationException $exception) {
            $this->feedback = collect($exception->errors())->flatten()->first() ?? 'No se pudo asignar la mesa.';
        } catch (\Throwable $exception) {
            $this->feedback = $exception->getMessage();
        }
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
        if ($this->selectedGroup === null) {
            return Categoria::query()
                ->groups()
                ->where('activa', true)
                ->orderBy('nombre')
                ->get();
        }

        return Categoria::query()
            ->categories()
            ->where('activa', true)
            ->where('parent_id', $this->selectedGroup)
            ->orderBy('nombre')
            ->get();
    }

    public function getProductsProperty()
    {
        if ($this->selectedGroup === null) {
            return collect();
        }

        if ($this->category === 'combos') {
            return collect();
        }

        return Producto::query()
            ->with('categoria')
            ->where('disponibilidad', DisponibilidadProducto::DISPONIBLE->value)
            ->whereHas('categoria', fn ($query) => $query->where('activa', true)->where('parent_id', $this->selectedGroup))
            ->when($this->category !== 'all', fn ($query) => $query->where('categoria_id', $this->category))
            ->orderBy('nombre')
            ->get();
    }

    public function getCombosProperty()
    {
        if ($this->selectedGroup !== null && $this->category !== 'combos') {
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

    public function getActiveDetailsProperty()
    {
        return $this->pedido->detalles
            ->where('estado_linea', EstadoLineaPedido::ACTIVA)
            ->values();
    }

    public function getCanChargeProperty(): bool
    {
        return $this->pedido->estado_comercial === EstadoComercialPedido::ABIERTO
            && $this->activeDetails->isNotEmpty()
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

    public function getMesasProperty()
    {
        $activeStates = [
            EstadoComercialPedido::ABIERTO->value,
            EstadoComercialPedido::PENDIENTE_COBRO->value,
            EstadoComercialPedido::COBRADO->value,
        ];

        return Mesa::query()
            ->where('establecimiento_id', $this->establishment()->getKey())
            ->where('activa', true)
            ->where('zona', $this->mesaZona)
            ->with(['pedidos' => function ($query) use ($activeStates): void {
                $query
                    ->whereIn('estado_comercial', $activeStates)
                    ->latest('id');
            }])
            ->orderBy('numero')
            ->get();
    }

    public function getGroupProductCountsProperty(): array
    {
        return Categoria::query()
            ->categories()
            ->where('activa', true)
            ->join('productos', 'productos.categoria_id', '=', 'categorias.id')
            ->where('productos.disponibilidad', DisponibilidadProducto::DISPONIBLE->value)
            ->groupBy('categorias.parent_id')
            ->pluck(DB::raw('COUNT(*)'), 'parent_id')
            ->toArray();
    }

    public function getSelectedGroupNameProperty(): ?string
    {
        if ($this->selectedGroup === null) {
            return null;
        }

        return Categoria::query()
            ->where('id', $this->selectedGroup)
            ->value('nombre');
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
