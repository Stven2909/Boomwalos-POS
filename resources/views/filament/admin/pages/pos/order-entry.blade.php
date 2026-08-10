<x-filament-panels::page>
    <div class="bw-pos-page bw-pos-order-page">
        @include('filament.admin.components.pos-header', [
            'backUrl' => \App\Filament\Pages\Pos\ServiceSelection::getUrl(),
            'centerLabel' => $pedido->tipo_pedido?->label() === 'Mesa'
                ? 'MESA ' . $pedido->mesa?->numero . ' · EN EL LOCAL'
                : 'PARA LLEVAR · MOSTRADOR',
            'rightLabel' => $pedido->estado_comercial?->value === 'COBRADO'
                ? 'COBRADO · PENDIENTE DE ENTREGA'
                : 'CUENTA ABIERTA',
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
                <div id="pos-catalog-toolbar" class="bw-pos-catalog-toolbar">
                <label class="bw-pos-search-field">
                    <x-heroicon-o-magnifying-glass class="h-5 w-5" />
                    <input type="search" wire:model.live.debounce.250ms="search" placeholder="Buscar producto o combo" aria-label="Buscar producto o combo">
                </label>

                <nav class="bw-pos-category-tabs" aria-label="Categorías de productos">
                    <button type="button" wire:click="selectCategory('all')" x-on:click="$nextTick(() => document.getElementById('pos-catalog-toolbar')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))" class="{{ $category === 'all' ? 'is-active' : '' }}">Todo</button>
                    <button type="button" wire:click="selectCategory('combos')" x-on:click="$nextTick(() => document.getElementById('pos-catalog-toolbar')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))" class="{{ $category === 'combos' ? 'is-active' : '' }}">Combos</button>
                    @foreach ($this->categories as $categoria)
                        <button type="button" wire:click="selectCategory('{{ $categoria->getKey() }}')" x-on:click="$nextTick(() => document.getElementById('pos-catalog-toolbar')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))" class="{{ (string) $category === (string) $categoria->getKey() ? 'is-active' : '' }}">
                            {{ $categoria->nombre }}
                        </button>
                    @endforeach
                </nav>
                </div>

                @if ($category !== 'combos')
                <section class="bw-pos-product-grid" aria-label="Productos disponibles">
                    @forelse ($this->products as $producto)
                        <article class="bw-pos-product-card">
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
                            <button type="button" wire:click="addProduct({{ $producto->getKey() }})" class="bw-pos-add-button">
                                <x-heroicon-o-plus class="h-4 w-4" /> Añadir
                            </button>
                        </article>
                    @empty
                        <div class="bw-pos-empty-state">
                            <x-heroicon-o-magnifying-glass class="h-8 w-8" />
                            <strong>No encontramos productos disponibles.</strong>
                            <span>Prueba otra búsqueda o revisa el catálogo.</span>
                        </div>
                    @endforelse
                </section>
                @endif

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
                @elseif ($category === 'combos')
                    <div class="bw-pos-empty-state bw-pos-combo-empty-state">
                        <x-heroicon-o-squares-2x2 class="h-8 w-8" />
                        <strong>No hay combos disponibles.</strong>
                        <span>El administrador puede crear o activar combos desde el catÃ¡logo.</span>
                    </div>
                @endif
                @else
                    <div class="bw-pos-read-only-state" role="status">
                        <x-heroicon-o-check-badge class="h-7 w-7" />
                        <strong>Pedido cobrado</strong>
                        <span>La cuenta está pendiente de entrega. No se pueden agregar productos.</span>
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

                @if ($feedback)
                    <div class="bw-pos-feedback" role="status">{{ $feedback }}</div>
                @endif

                @if ($undoLine)
                    <div class="bw-pos-undo" role="status">
                        <span>{{ $undoLine['nombre'] }} eliminado</span>
                        <button type="button" wire:click="undoRemove">Deshacer</button>
                    </div>
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
                        <x-heroicon-o-check-circle class="h-5 w-5" />
                        <span>Cobrado · pendiente de entrega</span>
                    </div>
                @elseif ($this->canCharge)
                    <button type="button" wire:click="openCharge" class="bw-pos-charge-button">
                        <x-heroicon-o-credit-card class="h-5 w-5" />
                        Cobrar cuenta
                    </button>
                @endif

                @if (! $this->isReadOnly)
                    <button
                        type="button"
                        wire:click="sendToKitchen"
                        class="bw-pos-kitchen-button"
                        @disabled($this->pendingDetails->isEmpty())
                    >
                        <x-heroicon-o-paper-airplane class="h-5 w-5" />
                        {{ $this->sentDetails->isNotEmpty() ? 'Enviar nueva tanda' : 'Enviar a cocina' }}
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
    </div>
</x-filament-panels::page>
