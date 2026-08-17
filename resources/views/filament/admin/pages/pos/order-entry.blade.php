<x-filament-panels::page>
    <div class="bw-pos-page bw-pos-order-page">
        @include('filament.admin.components.pos-header', [
            'backUrl' => \App\Filament\Pages\Pos\ServiceSelection::getUrl(),
            'centerLabel' => $pedido->tipo_pedido?->label() === 'Mesa'
                ? 'MESA ' . $pedido->mesa?->numero . ' · EN EL LOCAL'
                : 'PARA LLEVAR · MOSTRADOR',
            'rightLabel' => $pedido->estado_comercial?->value === 'PENDIENTE_COBRO'
                ? 'ENVIADO A CAJA · ESPERA COBRO'
                : ($pedido->estado_comercial?->value === 'COBRADO'
                    ? 'COBRADO · PENDIENTE DE ENTREGA'
                    : ($pedido->estado_comercial?->value === 'CANCELADO'
                        ? 'CANCELADO'
                        : 'CUENTA ABIERTA')),
        ])

        <main class="bw-pos-order-main">
            <section class="bw-pos-order-content" aria-labelledby="order-title">
                <div class="bw-pos-order-heading">
                    <div>
                        <span class="bw-pos-step-label">PASO 3 DE 5 · TOMAR ORDEN</span>
                        <h1 id="order-title">
                            Tomar orden
                            @if ($pedido->mesa)
                                · Mesa {{ $pedido->mesa->numero }}
                            @endif
                        </h1>
                    </div>
                    <span class="bw-pos-order-tracking">{{ $pedido->numero_seguimiento }}</span>
                </div>

                @if (! $this->isReadOnly)

                @if ($selectedGroup === null)
                    <div class="bw-pos-group-intro">
                        <p>Selecciona una categoría para comenzar a agregar productos</p>
                    </div>
                    <nav class="bw-pos-group-grid" aria-label="Grupos de productos">
                        @foreach ($this->categories as $grupo)
                            <button type="button" wire:click="selectGroup('{{ $grupo->getKey() }}')" class="bw-pos-group-card">
                                <span class="group-icon">
                                    @if ($grupo->iconoType() === 'image')
                                        <img src="{{ $grupo->iconoUrl() }}" alt="{{ $grupo->nombre }}" class="h-10 w-10 object-contain">
                                    @else
                                        {{ $grupo->icono ?: '📂' }}
                                    @endif
                                </span>
                                <strong class="group-name">{{ $grupo->nombre }}</strong>
                                <span class="group-count">{{ $this->groupProductCounts[$grupo->getKey()] ?? 0 }} productos</span>
                            </button>
                        @endforeach

                        @if ($this->combos->isNotEmpty())
                            <button type="button" wire:click="selectGroup('combos')" class="bw-pos-group-card bw-pos-group-card--combos">
                                <span class="group-icon">🎉</span>
                                <strong class="group-name">Combos</strong>
                                <span class="group-count">{{ $this->combos->count() }} disponibles</span>
                            </button>
                        @endif
                    </nav>

                @elseif ($selectedGroup === 'combos')
                    <div id="pos-catalog-toolbar" class="bw-pos-catalog-toolbar">
                        <button type="button" wire:click="backToGroups" class="bw-pos-back-button">
                            <x-heroicon-o-arrow-left class="h-4 w-4" /> Volver a grupos
                        </button>
                    </div>

                    @if ($this->combos->isNotEmpty())
                        <section class="bw-pos-combo-section" aria-labelledby="combos-title">
                            <div class="bw-pos-catalog-section-heading">
                                <div>
                                    <span class="bw-pos-step-label">CONFIGURABLES</span>
                                    <h2 id="combos-title">Combos para compartir</h2>
                                </div>
                                <span>{{ $this->combos->count() }} disponibles</span>
                            </div>

                            <div class="bw-pos-combo-grid">
                                @foreach ($this->combos as $combo)
                                    <article class="bw-pos-combo-card">
                                        <span class="bw-pos-product-image bw-pos-combo-image" aria-hidden="true">
                                            @if ($combo->imageUrl())
                                                <img src="{{ $combo->imageUrl() }}" alt="" onerror="this.hidden = true; this.nextElementSibling.hidden = false;">
                                                <x-heroicon-o-squares-2x2 class="h-9 w-9" hidden />
                                            @else
                                                <x-heroicon-o-squares-2x2 class="h-9 w-9" />
                                            @endif
                                        </span>
                                        <div class="bw-pos-product-copy">
                                            <strong>{{ $combo->nombre }}</strong>
                                            <span>{{ $combo->opcionesCombo->map(fn ($option): string => $option->cantidad_requerida . ' ' . $option->nombre)->implode(' · ') }}</span>
                                            <b>{{ $this->money($combo->precio_fijo) }}</b>
                                        </div>
                                        <button type="button" wire:click="openCombo({{ $combo->getKey() }})" class="bw-pos-add-button bw-pos-combo-button">
                                            <x-heroicon-o-adjustments-horizontal class="h-4 w-4" /> Configurar
                                        </button>
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @else
                        <div class="bw-pos-empty-state bw-pos-combo-empty-state">
                            <x-heroicon-o-squares-2x2 class="h-8 w-8" />
                            <strong>No hay combos disponibles.</strong>
                            <span>El administrador puede crear o activar combos desde el catálogo.</span>
                        </div>
                    @endif

                @else
                    <div id="pos-catalog-toolbar" class="bw-pos-catalog-toolbar">
                        <button type="button" wire:click="backToGroups" class="bw-pos-back-button">
                            <x-heroicon-o-arrow-left class="h-4 w-4" /> Volver a grupos
                        </button>

                        <nav class="bw-pos-category-tabs" aria-label="Subcategorías">
                            <button type="button" wire:click="selectCategory('all')" class="{{ $category === 'all' ? 'is-active' : '' }}">Todo</button>
                            @foreach ($this->categories as $subcat)
                                <button type="button" wire:click="selectCategory('{{ $subcat->getKey() }}')" class="{{ (string) $category === (string) $subcat->getKey() ? 'is-active' : '' }}">
                                    {{ $subcat->nombre }}
                                </button>
                            @endforeach
                        </nav>
                    </div>

                    <section class="bw-pos-product-grid" aria-label="Productos disponibles">
                        @forelse ($this->products as $producto)
                            <article class="bw-pos-product-card" wire:click="addProduct({{ $producto->getKey() }})" tabindex="0" role="button" aria-label="Agregar {{ $producto->nombre }}">
                                <span class="bw-pos-product-image" aria-hidden="true">
                                    @if ($producto->imageUrl())
                                        <img src="{{ $producto->imageUrl() }}" alt="" onerror="this.hidden = true; this.nextElementSibling.hidden = false;">
                                        <x-heroicon-o-shopping-bag class="h-9 w-9" hidden />
                                    @else
                                        <x-heroicon-o-shopping-bag class="h-9 w-9" />
                                    @endif
                                </span>
                                <div class="bw-pos-product-copy">
                                    <strong>{{ $producto->nombre }}</strong>
                                    <span>{{ $producto->categoria?->nombre }}</span>
                                    <b>{{ $this->money($producto->precio) }}</b>
                                </div>
                                <span class="bw-pos-product-add-indicator" aria-hidden="true">
                                    <x-heroicon-o-plus class="h-5 w-5" />
                                </span>
                            </article>
                        @empty
                            <div class="bw-pos-empty-state">
                                <x-heroicon-o-shopping-bag class="h-8 w-8" />
                                <strong>No hay productos disponibles.</strong>
                                <span>Revisa el catálogo o selecciona otra subcategoría.</span>
                            </div>
                        @endforelse
                    </section>
                @endif
                @else
                    <div class="bw-pos-read-only-state" role="status">
                        @if ($pedido->estado_comercial?->value === 'PENDIENTE_COBRO')
                            <x-heroicon-o-banknotes class="h-7 w-7" />
                            <strong>Enviado a caja</strong>
                            <span>La cuenta quedó registrada y espera el cobro en caja. No se pueden agregar productos.</span>
                        @elseif ($pedido->estado_comercial?->value === 'CANCELADO')
                            <x-heroicon-o-x-circle class="h-7 w-7" />
                            <strong>Pedido cancelado</strong>
                            <span>Este pedido fue cancelado y quedó registrado en auditoría.</span>
                        @else
                            <x-heroicon-o-check-badge class="h-7 w-7" />
                            <strong>Pedido cobrado</strong>
                            <span>La cuenta está pendiente de entrega. No se pueden agregar productos.</span>
                        @endif
                    </div>
                @endif
            </section>

            <aside class="bw-pos-order-summary" aria-labelledby="current-order-title">
                <div class="bw-pos-summary-heading">
                    <div>
                        <span class="bw-pos-step-label">PEDIDO ACTUAL</span>
                        <h2 id="current-order-title">Tu orden</h2>
                    </div>
                    <span class="bw-pos-summary-count">{{ $pedido->detalles->where('estado_linea', \App\Enums\EstadoLineaPedido::ACTIVA)->sum('cantidad') }} ítems</span>
                </div>

                @if (! $this->isReadOnly)
                    <button type="button" wire:click="openMesaPicker" class="bw-pos-assign-table">
                        @if ($pedido->mesa)
                            <x-heroicon-o-table-cells class="h-5 w-5" />
                            Mesa {{ $pedido->mesa->numero }} · Cambiar
                        @else
                            <x-heroicon-o-table-cells class="h-5 w-5" />
                            Asignar mesa (opcional)
                        @endif
                    </button>
                @endif

                @if ($feedback)
                    <div class="bw-pos-feedback" role="status">{{ $feedback }}</div>
                @endif



                <div class="bw-pos-summary-lines">
                    @forelse ($this->pendingDetails as $line)
                        <div class="bw-pos-summary-line">
                            <div class="bw-pos-line-info">
                                <strong>{{ $line->combo?->nombre ?? $line->producto?->nombre }}</strong>
                                @if ($line->combo_id)
                                    <span>{{ $this->comboLineSummary($line) }}</span>
                                @endif
                                <span>{{ $this->money($line->precio_unitario) }} c/u</span>
                            </div>
                            @if (! $this->isReadOnly)
                            <div class="bw-pos-line-controls">
                                <button type="button" wire:click="decrementLine({{ $line->getKey() }})" aria-label="Disminuir cantidad">
                                    <x-heroicon-o-minus class="h-4 w-4" />
                                </button>
                                <b>{{ $line->cantidad }}</b>
                                <button type="button" wire:click="incrementLine({{ $line->getKey() }})" aria-label="Aumentar cantidad">
                                    <x-heroicon-o-plus class="h-4 w-4" />
                                </button>
                                @if ($line->combo_id)
                                    <button type="button" wire:click="editCombo({{ $line->getKey() }})" aria-label="Editar combo">
                                        <x-heroicon-o-pencil-square class="h-4 w-4" />
                                    </button>
                                @endif
                                <button type="button" wire:click="removeLine({{ $line->getKey() }})" class="is-danger" aria-label="Eliminar producto">
                                    <x-heroicon-o-trash class="h-4 w-4" />
                                </button>
                            </div>
                            @endif
                            <b class="bw-pos-line-total">{{ $this->money((float) $line->precio_unitario * $line->cantidad) }}</b>
                        </div>
                    @empty
                        <div class="bw-pos-summary-empty">
                            <x-heroicon-o-shopping-bag class="h-7 w-7" />
                            <span>Aún no agregas productos.</span>
                        </div>
                    @endforelse

                    @if ($this->sentDetails->isNotEmpty())
                        <div class="bw-pos-sent-heading">Enviados a cocina</div>
                        @foreach ($this->sentDetails as $line)
                            <div class="bw-pos-summary-line is-sent {{ $line->estado_linea === \App\Enums\EstadoLineaPedido::CANCELADA ? 'is-cancelled' : '' }}">
                                <div class="bw-pos-line-info">
                                    <strong>{{ $line->combo?->nombre ?? $line->producto?->nombre }}</strong>
                                    @if ($line->combo_id)
                                        <span>{{ $this->comboLineSummary($line) }}</span>
                                    @endif
                                    <span>Tanda {{ $line->tanda?->numero_tanda }} · {{ $line->estado_linea?->label() }}</span>
                                </div>
                                <b class="bw-pos-line-quantity">×{{ $line->cantidad }}</b>
                                <b class="bw-pos-line-total">{{ $line->estado_linea === \App\Enums\EstadoLineaPedido::ACTIVA ? $this->money((float) $line->precio_unitario * $line->cantidad) : '$0.00' }}</b>
                                @can('cancelar_pedido')
                                    @if (! $this->isReadOnly && $line->estado_linea === \App\Enums\EstadoLineaPedido::ACTIVA)
                                        <button type="button" wire:click="cancelSentLine({{ $line->getKey() }})" wire:confirm="¿Anular este producto enviado a cocina?" class="bw-pos-cancel-line">Anular</button>
                                    @endif
                                @endcan
                            </div>
                        @endforeach
                    @endif
                </div>

                <div class="bw-pos-summary-total">
                    <span>Total</span>
                    <strong>{{ $this->money($this->total) }}</strong>
                </div>

                @if ($this->isReadOnly)
                    <div class="bw-pos-paid-status" role="status">
                        @if ($pedido->estado_comercial?->value === 'CANCELADO')
                            <x-heroicon-o-x-circle class="h-5 w-5" />
                            <span>Cancelado</span>
                        @else
                            <x-heroicon-o-check-circle class="h-5 w-5" />
                            <span>
                                {{ $pedido->estado_comercial?->value === 'PENDIENTE_COBRO'
                                    ? 'Enviado a caja · esperando cobro'
                                    : 'Cobrado · pendiente de entrega' }}
                            </span>
                        @endif
                    </div>
                @elseif ($this->isDeviceOrder)
                    <button
                        type="button"
                        wire:click="sendToCashRegister"
                        class="bw-pos-charge-button"
                        @disabled($this->activeDetails->isEmpty())
                    >
                        <x-heroicon-o-banknotes class="h-5 w-5" />
                        Enviar cuenta a caja
                    </button>
                @elseif ($this->canCharge)
                    <button type="button" wire:click="openCharge" class="bw-pos-charge-button">
                        <x-heroicon-o-fire class="h-5 w-5" />
                        Cobrar y enviar a cocina
                    </button>
                @endif
            </aside>
        </main>

        @if ($comboModalOpen && $this->selectedCombo)
            <div class="bw-pos-combo-modal" role="dialog" aria-modal="true" aria-labelledby="combo-modal-title">
                <button type="button" class="bw-pos-combo-backdrop" wire:click="closeCombo" aria-label="Cerrar selector de combo"></button>
                <section class="bw-pos-combo-dialog">
                    <header class="bw-pos-combo-dialog-header">
                        <div>
                            <span class="bw-pos-step-label">CONFIGURAR COMBO</span>
                            <h2 id="combo-modal-title">{{ $this->selectedCombo->nombre }}</h2>
                            <p>Elige las cantidades para cada grupo.</p>
                        </div>
                        <button type="button" wire:click="closeCombo" class="bw-pos-dialog-close" aria-label="Cerrar">
                            <x-heroicon-o-x-mark class="h-5 w-5" />
                        </button>
                    </header>

                    <div class="bw-pos-combo-options">
                        @foreach ($this->selectedCombo->opcionesCombo as $option)
                            <section class="bw-pos-combo-option" aria-labelledby="combo-option-{{ $option->getKey() }}">
                                <div class="bw-pos-combo-option-heading">
                                    <div>
                                        <h3 id="combo-option-{{ $option->getKey() }}">{{ $option->nombre }}</h3>
                                        <span>{{ $option->es_obligatorio ? 'Selecciona' : 'Opcional' }} {{ $option->cantidad_requerida }} unidades</span>
                                    </div>
                                    <strong>{{ $this->comboSelectionTotal($option->getKey()) }}/{{ $option->cantidad_requerida }}</strong>
                                </div>

                                <div class="bw-pos-combo-product-list">
                                    @foreach ($option->productos as $product)
                                        @php $selectedQuantity = (int) ($comboSelections[(string) $option->getKey()][(string) $product->getKey()] ?? 0); @endphp
                                        <div class="bw-pos-combo-product-row">
                                            <span>
                                                <strong>{{ $product->nombre }}</strong>
                                                <small>{{ $this->money($product->precio) }}</small>
                                            </span>
                                            <div class="bw-pos-combo-stepper">
                                                <button type="button" wire:click="changeComboSelection({{ $option->getKey() }}, {{ $product->getKey() }}, -1)" @disabled($selectedQuantity < 1) aria-label="Disminuir {{ $product->nombre }}">
                                                    <x-heroicon-o-minus class="h-4 w-4" />
                                                </button>
                                                <b>{{ $selectedQuantity }}</b>
                                                <button type="button" wire:click="changeComboSelection({{ $option->getKey() }}, {{ $product->getKey() }}, 1)" @disabled($this->comboSelectionTotal($option->getKey()) >= $option->cantidad_requerida) aria-label="Aumentar {{ $product->nombre }}">
                                                    <x-heroicon-o-plus class="h-4 w-4" />
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach
                    </div>

                    <footer class="bw-pos-combo-dialog-footer">
                        <strong>{{ $this->money($this->selectedCombo->precio_fijo) }}</strong>
                        <div>
                            <button type="button" wire:click="closeCombo" class="bw-pos-secondary-button">Cancelar</button>
                            <button type="button" wire:click="saveComboSelection" class="bw-pos-primary-button" @disabled(! $this->comboReady())>
                                <x-heroicon-o-check class="h-5 w-5" />
                                {{ $editingComboLineId ? 'Guardar cambios' : 'Agregar combo' }}
                            </button>
                        </div>
                    </footer>
                </section>
            </div>
        @endif

        @if ($mesaModalOpen)
            <div class="bw-pos-combo-modal" role="dialog" aria-modal="true" aria-labelledby="mesa-modal-title">
                <button type="button" class="bw-pos-combo-backdrop" wire:click="closeMesaPicker" aria-label="Cerrar selector de mesa"></button>
                <section class="bw-pos-combo-dialog bw-pos-mesa-dialog">
                    <header class="bw-pos-combo-dialog-header">
                        <div>
                            <span class="bw-pos-step-label">ASIGNAR MESA</span>
                            <h2 id="mesa-modal-title">Mesa para este pedido</h2>
                            <p>Al asignar una mesa, el pedido pasa a ser "en el local".</p>
                        </div>
                        <button type="button" wire:click="closeMesaPicker" class="bw-pos-dialog-close" aria-label="Cerrar">
                            <x-heroicon-o-x-mark class="h-5 w-5" />
                        </button>
                    </header>

                    <nav class="bw-pos-zone-tabs" aria-label="Zonas del establecimiento">
                        @foreach (\App\Enums\ZonaMesa::cases() as $zone)
                            <button
                                type="button"
                                wire:click="setMesaZona('{{ $zone->value }}')"
                                class="bw-pos-zone-tab {{ $mesaZona === $zone->value ? 'is-active' : '' }}"
                                aria-pressed="{{ $mesaZona === $zone->value ? 'true' : 'false' }}"
                            >
                                {{ $zone->label() }}
                            </button>
                        @endforeach
                    </nav>

                    <div class="bw-pos-mesa-grid-scroll">
                        <section class="bw-pos-table-grid" aria-label="Mesas disponibles">
                            @forelse ($this->mesas as $mesa)
                                @php
                                    $activeOrder = $mesa->pedidos->first();
                                    $isThisOrder = $activeOrder?->getKey() === $pedido->getKey();
                                    $isOccupied = $mesa->estado === \App\Enums\EstadoMesa::OCUPADA;
                                    $isBusy = $isOccupied && ! $isThisOrder;
                                    $isSelected = $pedido->mesa_id === $mesa->getKey();
                                @endphp
                                <button
                                    type="button"
                                    wire:click="assignTable({{ $mesa->getKey() }})"
                                    class="bw-pos-table-node {{ $isSelected ? 'is-selected' : ($isBusy ? 'is-occupied' : 'is-free') }}"
                                    @disabled($isBusy)
                                    aria-label="Mesa {{ $mesa->numero }}, {{ $isBusy ? 'ocupada' : 'disponible' }}"
                                >
                                    <span class="bw-pos-table-figure" aria-hidden="true">
                                        <span class="bw-pos-table-surface">
                                            <x-heroicon-o-table-cells class="h-9 w-9" />
                                        </span>
                                        <span class="bw-pos-table-leg bw-pos-table-leg-left"></span>
                                        <span class="bw-pos-table-leg bw-pos-table-leg-right"></span>
                                    </span>
                                    <strong class="bw-pos-table-number">MESA {{ str_pad($mesa->numero, 2, '0', STR_PAD_LEFT) }}</strong>
                                    <span class="bw-pos-table-state">{{ $isSelected ? 'Seleccionada' : ($isBusy ? 'Ocupada' : 'Libre') }}</span>
                                    @if ($isSelected)
                                        <span class="bw-pos-selected-mark" aria-hidden="true">
                                            <x-heroicon-o-check class="h-4 w-4" />
                                        </span>
                                    @endif
                                </button>
                            @empty
                                <div class="bw-pos-empty-state">
                                    <x-heroicon-o-table-cells class="h-8 w-8" />
                                    <strong>No hay mesas configuradas en esta zona.</strong>
                                </div>
                            @endforelse
                        </section>
                    </div>

                    <footer class="bw-pos-combo-dialog-footer">
                        <button type="button" wire:click="closeMesaPicker" class="bw-pos-secondary-button">Cancelar</button>
                    </footer>
                </section>
            </div>
        @endif
    </div>
</x-filament-panels::page>

