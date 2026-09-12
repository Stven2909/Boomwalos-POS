<?php

namespace App\Filament\Pages\Pos;

use App\Enums\DisponibilidadProducto;
use App\Enums\EstadoComercialPedido;
use App\Enums\EstadoImpresion;
use App\Enums\EstadoLineaPedido;
use App\Enums\MasaPupusa;
use App\Enums\MetodoPago;
use App\Enums\OrigenPedido;
use App\Enums\TipoPedido;
use App\Enums\ZonaMesa;
use App\Models\Categoria;
use App\Models\Combo;
use App\Models\DetallePedido;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Services\CobroService;
use App\Services\ConfiguracionService;
use App\Services\Orders\OrderLinePresenter;
use App\Services\PedidoService;
use Illuminate\Validation\ValidationException;

class OrderEntry extends PosPage
{
    private const RESERVED_COMBO_CATEGORY_NAME = 'combos';

    protected static ?string $slug = 'pos/orden/{pedido}';

    protected static ?string $title = 'Punto de Venta';

    protected string $view = 'filament.admin.pages.pos.order-entry';

    public Pedido $pedido;

    public string $search = '';

    public string $category = 'all';

    public ?string $selectedGroup = null;

    public ?string $feedback = null;

    public string $recallCode = '';

    public bool $recallModalOpen = false;

    public string $recallInput = '';

    public bool $manualAmountModalOpen = false;

    public bool $changeModalOpen = false;

    public float $lastTotal = 0.0;

    public float $lastReceived = 0.0;

    public float $lastChange = 0.0;

    public string $lastCode = '';

    public ?int $lastNewPedidoId = null;

    public string $metodoPago = MetodoPago::EFECTIVO->value;

    public string $montoRecibido = '';

    public bool $tarjetaAprobada = false;

    public bool $comboModalOpen = false;

    public ?int $selectedComboId = null;

    public ?int $editingComboLineId = null;

    public array $comboSelections = [];

    public bool $masaModalOpen = false;

    public ?int $selectedMasaProductId = null;

    public ?int $editingMasaLineId = null;

    public string $selectedMasa = '';

    public string $comboMasa = '';

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

        $feedback = session('pos_feedback');
        if ($feedback) {
            $this->feedback = $feedback;
        }
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

    public function discardEmptyAndBack(): void
    {
        try {
            app(PedidoService::class)->discardEmptyDraft($this->pedido, auth()->user());
            $this->redirect(ServiceSelection::getUrl());
        } catch (\Throwable $exception) {
            $this->feedback = $exception->getMessage();
        }
    }

    public function startFreshOrder(): void
    {
        try {
            app(PedidoService::class)->discardEmptyDraftsForUser(auth()->user());

            $pedido = app(PedidoService::class)->startOrder(
                TipoPedido::PARA_LLEVAR,
                auth()->user(),
                null,
                OrigenPedido::CAJA,
            );

            $this->redirect(self::getUrl(['pedido' => $pedido->getKey()]));
        } catch (ValidationException $exception) {
            $this->feedback = collect($exception->errors())->flatten()->first() ?? 'No se pudo iniciar el pedido.';
        } catch (\Throwable $exception) {
            $this->feedback = $exception->getMessage();
        }
    }

    public function recallOrder(): void
    {
        $code = trim($this->recallCode, " #\t\n\r\0\x0B");

        if ($code === '') {
            return;
        }

        $establishmentId = $this->establishment()->getKey();

        $query = Pedido::query()
            ->where('establecimiento_id', $establishmentId)
            ->whereIn('estado_comercial', [
                EstadoComercialPedido::ABIERTO->value,
                EstadoComercialPedido::PENDIENTE_COBRO->value,
            ])
            ->whereHas('detalles', fn ($details) => $details->where('estado_linea', EstadoLineaPedido::ACTIVA->value));

        $found = null;

        if (is_numeric($code)) {
            $num = (int) $code;
            $found = (clone $query)->where('codigo_corto', $num)->latest('id')->first()
                ?? (clone $query)->whereHas('mesa', fn ($q) => $q->where('numero', $num))->latest('id')->first();
        }

        if (! $found) {
            $found = (clone $query)->where('numero_seguimiento', 'LIKE', "%{$code}%")->latest('id')->first();
        }

        if ($found) {
            $this->recallCode = '';
            $this->redirect(self::getUrl(['pedido' => $found->getKey()]));

            return;
        }

        $this->feedback = "No se encontró ningún pedido pendiente con '{$this->recallCode}'.";
    }

    public function openRecallModal(): void
    {
        $this->recallInput = '';
        $this->recallModalOpen = true;
    }

    public function closeRecallModal(): void
    {
        $this->recallModalOpen = false;
        $this->recallInput = '';
    }

    public function recallNumpadDigit(string $digit): void
    {
        if (strlen($this->recallInput) < 10) {
            $this->recallInput .= $digit;
        }
    }

    public function recallNumpadBackspace(): void
    {
        $this->recallInput = mb_substr($this->recallInput, 0, -1);
    }

    public function recallNumpadClear(): void
    {
        $this->recallInput = '';
    }

    public function recallNumpadSubmit(): void
    {
        if (trim($this->recallInput) === '') {
            return;
        }

        $this->recallCode = $this->recallInput;
        $this->recallModalOpen = false;
        $this->recallOrder();
    }

    public function openManualAmountModal(): void
    {
        if ($this->metodoPago === MetodoPago::EFECTIVO->value) {
            $this->manualAmountModalOpen = true;
        }
    }

    public function closeManualAmountModal(): void
    {
        $this->manualAmountModalOpen = false;
    }

    public function selectProduct(int $productoId): void
    {
        $producto = Producto::query()->find($productoId);

        if (! $producto || $producto->disponibilidad !== DisponibilidadProducto::DISPONIBLE) {
            $this->feedback = 'Este producto ya no está disponible.';

            return;
        }

        if (! $producto->requiere_masa) {
            $this->addProduct($productoId);

            return;
        }

        $this->selectedMasaProductId = $producto->getKey();
        $this->editingMasaLineId = null;
        $this->selectedMasa = '';
        $this->masaModalOpen = true;
    }

    public function editProductMasa(int $lineId): void
    {
        $line = $this->pendingLine($lineId);

        if (! $line?->producto_id || ! $line->producto?->requiere_masa) {
            return;
        }

        $this->selectedMasaProductId = $line->producto_id;
        $this->editingMasaLineId = $line->getKey();
        $this->selectedMasa = (string) data_get($line->configuracion_producto, 'masa.codigo', '');
        $this->masaModalOpen = true;
    }

    public function closeMasaSelector(): void
    {
        $this->masaModalOpen = false;
        $this->selectedMasaProductId = null;
        $this->editingMasaLineId = null;
        $this->selectedMasa = '';
    }

    public function chooseMasa(string $masa): void
    {
        if (MasaPupusa::tryFrom(strtoupper(trim($masa))) !== null) {
            $this->selectedMasa = strtoupper(trim($masa));
        }
    }

    public function saveProductWithMasa(): void
    {
        $producto = $this->selectedMasaProduct;

        if (! $producto || $this->selectedMasa === '') {
            $this->feedback = 'Selecciona si la preparación será de maíz o de arroz.';

            return;
        }

        try {
            if ($this->editingMasaLineId) {
                $line = $this->pendingLine($this->editingMasaLineId);

                if ($line) {
                    app(PedidoService::class)->updatePendingProduct($this->pedido, $line, $this->selectedMasa, auth()->user());
                }
            } else {
                app(PedidoService::class)->addProduct($this->pedido, $producto, auth()->user(), $this->selectedMasa);
            }

            $this->feedback = null;
            $this->closeMasaSelector();
            $this->refreshPedido();
        } catch (ValidationException $exception) {
            $this->feedback = collect($exception->errors())->flatten()->first() ?? 'No se pudo agregar el producto.';
        }
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
        $this->comboMasa = '';
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
        $this->comboMasa = (string) data_get($line->configuracion_producto, 'masa.codigo', '');
        $this->comboSelections = $this->selectionState($combo, $line->seleccion_combo ?? [], $this->comboMasa);
        $this->comboModalOpen = true;
    }

    public function closeCombo(): void
    {
        $this->comboModalOpen = false;
        $this->selectedComboId = null;
        $this->editingComboLineId = null;
        $this->comboSelections = [];
        $this->comboMasa = '';
    }

    public function changeComboSelection(int $optionId, int $productId, int $delta): void
    {
        $combo = $this->selectedCombo;
        $option = $combo?->opcionesCombo->firstWhere('id', $optionId);

        if (! $combo || ! $option || ! $option->productos->contains('id', $productId)) {
            return;
        }

        $currentValue = $this->comboSelections[(string) $optionId][(string) $productId] ?? 0;
        if (is_array($currentValue)) {
            return;
        }

        $current = (int) $currentValue;
        $next = max(0, $current + $delta);
        $total = $this->comboSelectionTotal($optionId) - $current + $next;

        if ($delta > 0 && $total > (int) $option->cantidad_requerida) {
            return;
        }

        $this->comboSelections[(string) $optionId][(string) $productId] = $next;
    }

    public function changeComboSelectionMass(int $optionId, int $productId, string $masa, int $delta): void
    {
        $combo = $this->selectedCombo;
        $option = $combo?->opcionesCombo->firstWhere('id', $optionId);
        $product = $option?->productos->firstWhere('id', $productId);
        $mass = MasaPupusa::tryFrom(strtoupper(trim($masa)));

        if (! $combo || ! $option || ! $product?->requiere_masa || ! $mass) {
            return;
        }

        $currentBuckets = $this->comboSelections[(string) $optionId][(string) $productId] ?? [];
        if (! is_array($currentBuckets)) {
            $currentBuckets = ['MAIZ' => 0, 'ARROZ' => 0];
        }

        $current = (int) ($currentBuckets[$mass->value] ?? 0);
        $next = max(0, $current + $delta);
        $total = $this->comboSelectionTotal($optionId) - $current + $next;

        if ($delta > 0 && $total > (int) $option->cantidad_requerida) {
            return;
        }

        $currentBuckets[$mass->value] = $next;
        $this->comboSelections[(string) $optionId][(string) $productId] = $currentBuckets;
    }

    public function saveComboSelection(): void
    {
        $combo = $this->selectedCombo;

        if (! $combo || ! $this->comboReady()) {
            $this->feedback = $combo && $this->comboRequiresMasa() && ! $this->comboMassesComplete()
                ? 'Define la masa de cada pupusa del combo.'
                : 'Completa las cantidades requeridas del combo antes de agregarlo.';

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

    public function addProduct(int $productoId, ?string $masa = null): void
    {
        try {
            $producto = Producto::query()->findOrFail($productoId);
            app(PedidoService::class)->addProduct($this->pedido, $producto, auth()->user(), $masa);
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

        try {
            app(PedidoService::class)->removePendingLine($this->pedido, $line);

            $this->pedido = $this->pedido->fresh();
            $hasActiveLines = $this->pedido->detalles()
                ->where('estado_linea', EstadoLineaPedido::ACTIVA->value)
                ->exists();

            if (! $hasActiveLines) {
                app(PedidoService::class)->discardEmptyDraft($this->pedido, auth()->user(), 'Se retiró el último producto del pedido.');
                $this->redirect(ServiceSelection::getUrl());

                return;
            }

            $this->feedback = null;
            $this->refreshPedido();
        } catch (ValidationException $exception) {
            $this->feedback = collect($exception->errors())->flatten()->first();
        }
    }

    public function sendToKitchen(): void
    {
        try {
            $job = app(PedidoService::class)->sendPendingBatch($this->pedido, auth()->user());
            $printMessage = $job->estado === EstadoImpresion::PENDIENTE
                ? ' Comanda en cola de impresión.'
                : ' No hay una impresora de comanda configurada.';
            $this->feedback = "Comanda enviada.{$printMessage} La cuenta sigue abierta.";
            $this->refreshPedido();
        } catch (ValidationException $exception) {
            $this->feedback = collect($exception->errors())->flatten()->first() ?? 'No se pudo enviar el pedido.';
        }
    }

    public function sendToCashRegister(): void
    {
        try {
            app(PedidoService::class)->sendToCashRegister($this->pedido, auth()->user());
            session()->flash('pos_feedback', 'La cuenta fue enviada a caja. Espera el cobro en el mostrador.');
            $this->redirect(ServiceSelection::getUrl());
        } catch (ValidationException $exception) {
            $this->feedback = collect($exception->errors())->flatten()->first() ?? 'No se pudo enviar el pedido a caja.';
        }
    }

    public function saveAndReturnTables(): void
    {
        $this->redirect(TableSelection::getUrl([
            'tipo' => TipoPedido::MESA->value,
            'entrada' => 'mesas',
        ]));
    }

    public function updatedMetodoPago(string $metodo): void
    {
        if ($metodo === MetodoPago::TARJETA->value) {
            $this->montoRecibido = number_format($this->total, 2, '.', '');
        }
    }

    public function ingresarDigito(string $digito): void
    {
        if ($this->metodoPago !== MetodoPago::EFECTIVO->value) {
            return;
        }

        if (! preg_match('/^[0-9.]$/', $digito)) {
            return;
        }

        $current = $this->montoRecibido;

        if ($digito === '.') {
            if (str_contains($current, '.')) {
                return;
            }

            $this->montoRecibido = $current === '' ? '0.' : $current.'.';

            return;
        }

        $dotPosition = strpos($current, '.');

        if ($dotPosition !== false && strlen(substr($current, $dotPosition + 1)) >= 2) {
            return;
        }

        $this->montoRecibido = $current === '0' ? $digito : $current.$digito;
    }

    public function borrarDigito(): void
    {
        if ($this->metodoPago === MetodoPago::EFECTIVO->value) {
            $this->montoRecibido = mb_substr($this->montoRecibido, 0, -1);
        }
    }

    public function limpiarMonto(): void
    {
        if ($this->metodoPago === MetodoPago::EFECTIVO->value) {
            $this->montoRecibido = '';
        }
    }

    public function usarMontoRapido(string $monto): void
    {
        if ($this->metodoPago === MetodoPago::EFECTIVO->value) {
            $this->montoRecibido = $monto;
        }
    }

    public function usarMontoExacto(): void
    {
        $this->montoRecibido = number_format($this->total, 2, '.', '');
    }

    public function fastCharge(string $monto): void
    {
        $this->metodoPago = MetodoPago::EFECTIVO->value;
        $this->montoRecibido = $monto;
        $this->charge();
    }

    public function charge(): void
    {
        if (! $this->canCharge) {
            $this->feedback = 'Agrega al menos un producto antes de cobrar.';

            return;
        }

        try {
            $tarjeta = $this->metodoPago === MetodoPago::TARJETA->value
                ? ['aprobada' => $this->tarjetaAprobada]
                : null;

            $montoAEnviar = $this->metodoPago === MetodoPago::EFECTIVO->value
                ? ($this->montoRecibido !== '' ? $this->montoRecibido : number_format($this->total, 2, '.', ''))
                : null;

            [, , $ticketResult] = app(CobroService::class)->chargeAndSend(
                $this->pedido,
                MetodoPago::tryFrom($this->metodoPago) ?? MetodoPago::EFECTIVO,
                $montoAEnviar,
                auth()->user(),
                $tarjeta,
            );

            $this->lastTotal = (float) $this->total;
            $this->lastReceived = (float) ($montoAEnviar ?: $this->total);
            $this->lastChange = (float) $this->change;
            $this->lastCode = (string) ($this->pedido->codigoCortoLabel() ?: 'ORDEN #'.$this->pedido->id);

            // La siguiente orden se crea solo cuando el cajero la solicita de forma explícita.
            // Crear este registro aquí dejaba pedidos ABIERTO vacíos en la operación.
            $this->lastNewPedidoId = null;

            $this->manualAmountModalOpen = false;
            $this->changeModalOpen = true;
        } catch (ValidationException $exception) {
            $this->feedback = collect($exception->errors())->flatten()->first() ?? 'No se pudo registrar el pago.';
        } catch (\Throwable $exception) {
            $this->feedback = $exception->getMessage();
        }
    }

    public function acceptChangeAndNext(): void
    {
        $this->changeModalOpen = false;
        $this->redirect(ServiceSelection::getUrl());
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
                : 'Mesa '.$mesa->numero.' asignada. El pedido pasó a "en el local".';
            $this->mesaModalOpen = false;
            $this->refreshPedido();
        } catch (ValidationException $exception) {
            $this->feedback = collect($exception->errors())->flatten()->first() ?? 'No se pudo asignar la mesa.';
        } catch (\Throwable $exception) {
            $this->feedback = $exception->getMessage();
        }
    }

    public function removeTable(): void
    {
        $this->feedback = 'No se puede cambiar el flujo de una cuenta activa. Finaliza o cancela el pedido y crea uno nuevo.';
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

    private $cachedProducts = [];

    private $cachedRootCategories = null;

    private $cachedCategories = null;

    private $cachedAvailableCombos = null;

    public function getCategoriesProperty()
    {
        if ($this->cachedCategories === null) {
            $this->cachedCategories = Categoria::query()
                ->where('activa', true)
                ->orderBy('nombre')
                ->limit(100)
                ->get();
        }

        return $this->cachedCategories;
    }

    public function getRootCategoriesProperty()
    {
        if ($this->cachedRootCategories === null) {
            $this->cachedRootCategories = Categoria::query()
                ->groups()
                ->where('activa', true)
                ->orderBy('nombre')
                ->get();
        }

        return $this->cachedRootCategories;
    }

    public function getProductsProperty()
    {
        if ($this->category === 'combos') {
            return collect();
        }

        $cacheKey = $this->category.'|'.$this->search;
        if (! isset($this->cachedProducts[$cacheKey])) {
            $this->cachedProducts[$cacheKey] = Producto::query()
                ->with('categoria')
                ->where('disponibilidad', DisponibilidadProducto::DISPONIBLE->value)
                ->whereHas('categoria', fn ($query) => $query->where('activa', true))
                ->when($this->category !== 'all', function ($query) {
                    $cat = Categoria::find($this->category);
                    if ($cat && $cat->isGroup()) {
                        $childIds = Categoria::where('parent_id', $cat->id)->pluck('id')->all();
                        $query->whereIn('categoria_id', array_merge([$cat->id], $childIds));
                    } else {
                        $query->where('categoria_id', $this->category);
                    }
                })
                ->when($this->search !== '', function ($query) {
                    $query->where('nombre', 'LIKE', '%'.$this->search.'%');
                })
                ->orderBy('nombre')
                ->get();
        }

        return $this->cachedProducts[$cacheKey];
    }

    public function getCombosProperty()
    {
        return $this->availableCombos();
    }

    public function getHasCombosProperty(): bool
    {
        return $this->availableCombos()->isNotEmpty();
    }

    public function getSelectedComboProperty(): ?Combo
    {
        if (! $this->selectedComboId) {
            return null;
        }

        return $this->availableCombos()->firstWhere('id', $this->selectedComboId);
    }

    public function getSelectedMasaProductProperty(): ?Producto
    {
        return $this->selectedMasaProductId ? Producto::query()->find($this->selectedMasaProductId) : null;
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

    public function getChangeProperty(): float
    {
        if ($this->metodoPago !== MetodoPago::EFECTIVO->value || ! is_numeric($this->montoRecibido)) {
            return 0.00;
        }

        return max(0, round((float) $this->montoRecibido - $this->total, 2));
    }

    public function getMontosRapidosProperty(): array
    {
        $total = $this->total;
        if ($total <= 0) {
            return ['5.00', '10.00', '20.00', '50.00', '100.00'];
        }

        $presets = [];
        // Exact
        $presets[] = number_format($total, 2, '.', '');

        // Round up to next 5, 10, 20, 50, 100
        $bills = [5, 10, 20, 50, 100];
        foreach ($bills as $bill) {
            if ($bill > $total) {
                $presets[] = number_format((float) $bill, 2, '.', '');
            }
        }

        foreach ($bills as $bill) {
            $formatted = number_format((float) $bill, 2, '.', '');
            if (! in_array($formatted, $presets, true)) {
                $presets[] = $formatted;
            }
        }

        return array_values(array_unique(array_slice($presets, 0, 5)));
    }

    public function getActiveDetailsProperty()
    {
        return $this->pedido->detalles
            ->where('estado_linea', EstadoLineaPedido::ACTIVA)
            ->values();
    }

    public function getCanChargeProperty(): bool
    {
        return $this->pedido->tipo_pedido === TipoPedido::PARA_LLEVAR
            && $this->pedido->estado_comercial === EstadoComercialPedido::ABIERTO
            && $this->activeDetails->isNotEmpty()
            && auth()->user()?->can('cobrar_pedido');
    }

    public function getIsTableFlowProperty(): bool
    {
        return $this->pedido->tipo_pedido === TipoPedido::MESA;
    }

    public function getCanRequestAccountProperty(): bool
    {
        return $this->isTableFlow
            && $this->pedido->estado_comercial === EstadoComercialPedido::ABIERTO
            && $this->activeDetails->isNotEmpty()
            && $this->pendingDetails->isEmpty();
    }

    public function getCanSubmitPaymentProperty(): bool
    {
        if (! $this->canCharge) {
            return false;
        }

        if ($this->metodoPago === MetodoPago::TARJETA->value) {
            return $this->tarjetaAprobada;
        }

        return true;
    }

    public function getIsDeviceOrderProperty(): bool
    {
        return $this->pedido->origen_pedido === OrigenPedido::DISPOSITIVO;
    }

    public function getIsReadOnlyProperty(): bool
    {
        return $this->pedido->estado_comercial !== EstadoComercialPedido::ABIERTO;
    }

    public function getSimboloMonedaProperty(): string
    {
        return (string) app(ConfiguracionService::class)->get('moneda.simbolo', '$');
    }

    public function getMesasProperty()
    {
        return Mesa::query()
            ->where('establecimiento_id', $this->establishment()->getKey())
            ->where('activa', true)
            ->where('zona', $this->mesaZona)
            ->with(['pedidos' => function ($query): void {
                $query
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
                    ->latest('id');
            }])
            ->orderBy('numero')
            ->get();
    }

    public function comboSelectionTotal(int $optionId): int
    {
        return collect($this->comboSelections[(string) $optionId] ?? [])->sum(
            fn ($quantity): int => is_array($quantity)
                ? collect($quantity)->sum(fn ($massQuantity): int => (int) $massQuantity)
                : (int) $quantity
        );
    }

    public function comboRequiresMasa(): bool
    {
        $combo = $this->selectedCombo;

        if (! $combo) {
            return false;
        }

        foreach ($combo->opcionesCombo as $option) {
            foreach ($option->productos as $product) {
                $selection = $this->comboSelections[(string) $option->getKey()][(string) $product->getKey()] ?? 0;
                $quantity = is_array($selection)
                    ? collect($selection)->sum(fn ($value): int => (int) $value)
                    : (int) $selection;

                if ($quantity > 0 && $product->requiere_masa) {
                    return true;
                }
            }
        }

        return false;
    }

    public function comboReady(): bool
    {
        $combo = $this->selectedCombo;

        if (! $combo) {
            return false;
        }

        $selectionsReady = $combo->opcionesCombo->every(function ($option): bool {
            $total = $this->comboSelectionTotal($option->getKey());

            return $option->es_obligatorio
                ? $total === (int) $option->cantidad_requerida
                : ($total === 0 || $total === (int) $option->cantidad_requerida);
        });

        return $selectionsReady && $this->comboMassesComplete();
    }

    public function comboMassesComplete(): bool
    {
        $combo = $this->selectedCombo;

        if (! $combo) {
            return false;
        }

        foreach ($combo->opcionesCombo as $option) {
            foreach ($option->productos as $product) {
                if (! $product->requiere_masa) {
                    continue;
                }

                $selection = $this->comboSelections[(string) $option->getKey()][(string) $product->getKey()] ?? 0;
                $quantity = is_array($selection)
                    ? collect($selection)->sum(fn ($value): int => (int) $value)
                    : (int) $selection;

                if ($quantity > 0 && ! is_array($selection)) {
                    return false;
                }

                if ($quantity > 0 && collect($selection)->keys()->filter(fn ($mass): bool => MasaPupusa::tryFrom((string) $mass) === null)->isNotEmpty()) {
                    return false;
                }
            }
        }

        return true;
    }

    public function comboLineSummary(DetallePedido $line): string
    {
        return app(OrderLinePresenter::class)->comboSummary($line);
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
        if ($this->cachedAvailableCombos === null) {
            $this->cachedAvailableCombos = Combo::query()
                ->with('opcionesCombo.productos')
                ->where('disponibilidad', DisponibilidadProducto::DISPONIBLE->value)
                ->orderBy('nombre')
                ->limit(100)
                ->get();
        }

        return $this->cachedAvailableCombos;
    }

    private function emptyComboSelection(Combo $combo): array
    {
        return $combo->opcionesCombo->mapWithKeys(fn ($option): array => [
            (string) $option->getKey() => $option->productos->mapWithKeys(fn (Producto $product): array => [
                (string) $product->getKey() => $product->requiere_masa
                    ? ['MAIZ' => 0, 'ARROZ' => 0]
                    : 0,
            ])->all(),
        ])->all();
    }

    private function selectionState(Combo $combo, array $storedSelection, string $defaultMass = ''): array
    {
        $state = $this->emptyComboSelection($combo);

        foreach ($storedSelection as $group) {
            $optionId = (string) ($group['opcion_combo_id'] ?? '');

            foreach ($group['items'] ?? [] as $item) {
                $productId = (string) ($item['producto_id'] ?? '');

                if (isset($state[$optionId][$productId])) {
                    $quantity = (int) ($item['cantidad'] ?? 0);
                    $storedMass = strtoupper((string) data_get($item, 'masa.codigo', $defaultMass));

                    if (is_array($state[$optionId][$productId])) {
                        if (MasaPupusa::tryFrom($storedMass)) {
                            $state[$optionId][$productId][$storedMass] += $quantity;
                        }
                    } else {
                        $state[$optionId][$productId] = $quantity;
                    }
                }
            }
        }

        return $state;
    }
}
