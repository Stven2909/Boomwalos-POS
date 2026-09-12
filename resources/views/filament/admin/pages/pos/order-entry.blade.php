<x-filament-panels::page>
    <div
        class="bw-pos-page bw-pos-order-page"
        x-data="{
            recallOpen: false,
            recallDigits: '',
            manualOpen: false,
            manualDigits: '',
            currentTotal: {{ (float) $this->total }},

            openRecall() {
                this.recallDigits = '';
                this.recallOpen = true;
            },
            closeRecall() {
                this.recallOpen = false;
            },
            pressRecall(d) {
                if (this.recallDigits.length < 6) this.recallDigits += d;
            },
            backspaceRecall() {
                this.recallDigits = this.recallDigits.slice(0, -1);
            },
            clearRecall() {
                this.recallDigits = '';
            },
            submitRecall() {
                if (this.recallDigits.trim() !== '') {
                    $wire.set('recallCode', this.recallDigits);
                    $wire.recallOrder();
                }
                this.recallOpen = false;
            },

            openManual() {
                this.manualDigits = '';
                this.manualOpen = true;
            },
            closeManual() {
                this.manualOpen = false;
            },
            pressManual(d) {
                if (d === '.' && this.manualDigits.includes('.')) return;
                if (this.manualDigits.length < 8) this.manualDigits += d;
            },
            backspaceManual() {
                this.manualDigits = this.manualDigits.slice(0, -1);
            },
            clearManual() {
                this.manualDigits = '';
            },
            get calculatedChange() {
                let num = parseFloat(this.manualDigits);
                if (isNaN(num) || num < this.currentTotal) return 0;
                return (num - this.currentTotal).toFixed(2);
            },
            submitManual() {
                if (this.manualDigits.trim() !== '') {
                    $wire.fastCharge(this.manualDigits);
                }
                this.manualOpen = false;
            }
        }"
        @keydown.window.escape="recallOpen = false; manualOpen = false"
    >
        @include('filament.admin.components.pos-header', [
            'backUrl' => \App\Filament\Pages\Pos\ServiceSelection::getUrl(),
            'backAction' => 'discardEmptyAndBack',
            'backLabel' => 'Inicio',
            'centerLabel' => $pedido->tipo_pedido?->label() === 'Mesa'
                ? 'MESA ' . $pedido->mesa?->numero . ' · EN EL LOCAL'
                : 'PARA LLEVAR · MOSTRADOR',
            'rightLabel' => $this->actorName() . ' · ' . ($pedido->codigoCortoLabel() ?: 'ORDEN'),
        ])

        <main class="bw-pos-order-main">
            <!-- BARRA DE COMANDO SUPERIOR (RECALL + MESA + ACCIONES) -->
            <div class="bw-pos-command-bar">
                <div class="bw-pos-command-left">
                    <span class="bw-pos-order-badge">
                        <strong>{{ $pedido->codigoCortoLabel() ?: 'ORDEN' }}</strong>
                        <small>{{ $pedido->numero_seguimiento }}</small>
                    </span>

                    @if ($pedido->mesa)
                        <div class="bw-pos-table-pill is-active">
                            <x-heroicon-o-table-cells class="h-4 w-4 text-emerald-600" />
                            <span>Mesa {{ $pedido->mesa->numero }}</span>
                        </div>
                    @else
                        <div class="bw-pos-table-pill is-add">
                            <x-heroicon-o-shopping-bag class="h-4 w-4" />
                            <span>Para Llevar</span>
                        </div>
                    @endif
                </div>

                <!-- RECALL TOUCH TRIGGER (ALPINE 0ms) -->
                <div class="bw-pos-command-center">
                    <button type="button" @click="openRecall()" class="bw-pos-recall-touch-trigger" title="Abrir teclado táctil para cargar comanda">
                        <x-heroicon-o-bolt class="h-5 w-5 text-amber-500" />
                        <span class="bw-pos-recall-touch-label">{{ $recallCode ? '#' . $recallCode : 'Cargar #Ticket o Mesa' }}</span>
                        <span class="bw-pos-recall-touch-pill"><x-heroicon-o-pencil-square class="h-4 w-4" aria-hidden="true" /> <span>Digitar</span></span>
                    </button>
                </div>

                <div class="bw-pos-command-right">
                    @if ($this->isTableFlow)
                    <a href="{{ \App\Filament\Pages\Pos\TableSelection::getUrl(['tipo' => 'MESA', 'entrada' => 'mesas']) }}" class="bw-pos-top-action-btn" title="Ver plano de mesas">
                        <x-heroicon-o-map class="h-4 w-4" />
                        <span>Mesas</span>
                    </a>
                    @endif
                    @if (! $this->isTableFlow)
                    <button type="button" wire:click="startFreshOrder" class="bw-pos-top-action-btn is-highlight" title="Nueva orden limpia">
                        <x-heroicon-o-plus-circle class="h-4 w-4" />
                        <span>Nueva Orden</span>
                    </button>
                    @endif
                </div>
            </div>

            <!-- COLUMNA IZQUIERDA: CATÁLOGO TÁCTIL (65%) -->
            <section class="bw-pos-order-content" aria-labelledby="catalog-heading">
                <div class="bw-pos-catalog-nav-bar">
                    <nav class="bw-pos-category-pills" aria-label="Categorías">
                        <button type="button" wire:key="cat-all" wire:click="selectCategory('all')" class="bw-pos-cat-pill {{ $category === 'all' ? 'is-active' : '' }}">
                            <x-heroicon-o-fire class="h-4 w-4" aria-hidden="true" /> <span>Todo</span>
                        </button>
                        @foreach ($this->rootCategories as $cat)
                            <button type="button" wire:key="cat-{{ $cat->getKey() }}" wire:click="selectCategory('{{ $cat->getKey() }}')" class="bw-pos-cat-pill {{ (string) $category === (string) $cat->getKey() ? 'is-active' : '' }}">
                                <x-heroicon-o-squares-2x2 class="h-4 w-4" aria-hidden="true" /> <span>{{ $cat->nombre }}</span>
                            </button>
                        @endforeach
                        @if ($this->hasCombos)
                            <button type="button" wire:key="cat-combos" wire:click="selectCategory('combos')" class="bw-pos-cat-pill is-combo {{ $category === 'combos' ? 'is-active' : '' }}">
                                <x-heroicon-o-adjustments-horizontal class="h-4 w-4" aria-hidden="true" /> <span>Combos</span>
                            </button>
                        @endif
                    </nav>

                    <div class="bw-pos-search-wrapper">
                        <x-heroicon-o-magnifying-glass class="h-4 w-4 text-gray-400" />
                        <input
                            type="search"
                            wire:model.live.debounce.150ms="search"
                            placeholder="Buscar producto..."
                            class="bw-pos-search-input"
                            aria-label="Buscar productos"
                        >
                    </div>
                </div>

                @if (! $this->isReadOnly)
                    @if ($category === 'combos')
                        <!-- GRID DE COMBOS -->
                        <section class="bw-pos-combo-grid" aria-label="Combos disponibles">
                            @forelse ($this->availableCombos() as $combo)
                                <button type="button" wire:key="combo-{{ $combo->getKey() }}" class="bw-pos-combo-card" wire:click="openCombo({{ $combo->getKey() }})" aria-label="Configurar {{ $combo->nombre }}">
                                    <span class="bw-pos-product-image bw-pos-combo-image" aria-hidden="true">
                                        @if ($combo->imageUrl())
                                            <img src="{{ $combo->imageUrl() }}" alt="" onerror="this.hidden = true; this.nextElementSibling.hidden = false;">
                                            <x-heroicon-o-squares-2x2 class="h-8 w-8" hidden />
                                        @else
                                            <x-heroicon-o-squares-2x2 class="h-8 w-8" />
                                        @endif
                                    </span>
                                    <div class="bw-pos-product-copy bw-pos-combo-copy">
                                        <span class="bw-pos-combo-kicker">COMBO</span>
                                        <strong>{{ $combo->nombre }}</strong>
                                        <span class="bw-pos-combo-options">Incluye {{ $combo->opcionesCombo->map(fn ($option): string => $option->cantidad_requerida . ' ' . $option->nombre)->implode(' · ') }}</span>
                                        <b>{{ $this->money($combo->precio_fijo) }}</b>
                                    </div>
                                    <span class="bw-pos-add-button bw-pos-combo-button" aria-hidden="true">
                                        <x-heroicon-o-adjustments-horizontal class="h-4 w-4" /> <span>Configurar combo</span>
                                    </span>
                                </button>
                            @empty
                                <div class="bw-pos-empty-state">
                                    <x-heroicon-o-squares-2x2 class="h-8 w-8" />
                                    <strong>No hay combos disponibles.</strong>
                                </div>
                            @endforelse
                        </section>
                    @else
                        <!-- GRID DE PRODUCTOS EN 3 COLUMNAS TÁCTILES -->
                        <section class="bw-pos-product-grid" aria-label="Productos disponibles">
                            @forelse ($this->products as $producto)
                                <article wire:key="prod-{{ $producto->getKey() }}" class="bw-pos-product-card {{ $producto->requiere_masa ? 'is-mass-choice' : '' }}" @if (! $producto->requiere_masa) wire:click="selectProduct({{ $producto->getKey() }})" @keydown.enter="event.currentTarget.click()" @keydown.space.prevent="event.currentTarget.click()" tabindex="0" role="button" @endif aria-label="{{ $producto->requiere_masa ? 'Elegir masa para ' : 'Agregar ' }}{{ $producto->nombre }}">
                                    <span class="bw-pos-product-image {{ $producto->imageUrl() ? 'has-image' : 'is-placeholder' }}" aria-hidden="true">
                                        @if ($producto->imageUrl())
                                            <img src="{{ $producto->imageUrl() }}" alt="" onerror="this.hidden = true; this.nextElementSibling.hidden = false;">
                                            <x-heroicon-o-fire class="h-7 w-7" hidden />
                                        @else
                                            <x-heroicon-o-fire class="h-7 w-7" />
                                        @endif
                                    </span>
                                    <div class="bw-pos-product-copy">
                                        <strong>{{ $producto->nombre }}</strong>
                                        <span>{{ $producto->categoria?->nombre }}</span>
                                        @if ($producto->requiere_masa)
                                            <div class="bw-pos-product-mass-actions" aria-label="Elegir masa para {{ $producto->nombre }}">
                                                @foreach (\App\Enums\MasaPupusa::cases() as $masa)
                                                    <button type="button" wire:click="addProduct({{ $producto->getKey() }}, '{{ $masa->value }}')" class="bw-pos-product-mass-button">
                                                        {{ $masa->label() }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        @endif
                                        <b>{{ $this->money($producto->precio) }}</b>
                                    </div>
                                    @if (! $producto->requiere_masa)
                                        <span class="bw-pos-product-add-indicator" aria-hidden="true">
                                            <x-heroicon-o-plus class="h-4 w-4" />
                                        </span>
                                    @endif
                                </article>
                            @empty
                                <div class="bw-pos-empty-state">
                                    <x-heroicon-o-fire class="h-8 w-8" />
                                    <strong>No se encontraron productos.</strong>
                                </div>
                            @endforelse
                        </section>
                    @endif
                @else
                    <div class="bw-pos-read-only-state" role="status">
                        @if ($pedido->estado_comercial?->value === 'PENDIENTE_COBRO')
                            <x-heroicon-o-banknotes class="h-7 w-7" />
                            <strong>Enviado a caja</strong>
                            <span>La cuenta quedó registrada y espera cobro.</span>
                        @elseif ($pedido->estado_comercial?->value === 'CANCELADO')
                            <x-heroicon-o-x-circle class="h-7 w-7" />
                            <strong>Pedido cancelado</strong>
                        @else
                            <x-heroicon-o-check-badge class="h-7 w-7" />
                            <strong>Pedido cobrado</strong>
                            <span>Pendiente de entrega.</span>
                        @endif
                    </div>
                @endif
            </section>

            <!-- COLUMNA DERECHA: TICKET ACTIVO + FAST CASH TENDER (35%) -->
            <aside class="bw-pos-order-summary bw-pos-order-summary-allinone" aria-labelledby="current-order-title">
                <div class="bw-pos-summary-heading">
                    <div>
                        <span class="bw-pos-step-label">TICKET ACTIVO</span>
                        <h2 id="current-order-title">{{ $pedido->codigoCortoLabel() ?: 'ORDEN' }} · {{ $pedido->mesa ? 'Mesa ' . $pedido->mesa->numero : 'Para Llevar' }}</h2>
                    </div>
                    <span class="bw-pos-summary-count">{{ $pedido->detalles->where('estado_linea', \App\Enums\EstadoLineaPedido::ACTIVA)->sum('cantidad') }} ítems</span>
                </div>

                @if ($feedback)
                    <div class="bw-pos-feedback {{ str_contains(strtolower($feedback), 'exitoso') || str_contains(strtolower($feedback), 'enviada') ? 'is-success' : 'is-error' }}" role="status">
                        <span>{{ $feedback }}</span>
                    </div>
                @endif

                <!-- LISTA DE PRODUCTOS DEL TICKET CON STEPPERS AGRANDADOS (36px) -->
                <div class="bw-pos-summary-lines">
                    @forelse ($this->pendingDetails as $line)
                        <div wire:key="line-{{ $line->getKey() }}" class="bw-pos-summary-line">
                            <div class="bw-pos-line-info">
                                <strong>{{ $line->combo?->nombre ?? $line->producto?->nombre }}</strong>
                                @if ($line->combo_id)
                                    <span>{{ $this->comboLineSummary($line) }}</span>
                                @endif
                                @if (data_get($line->configuracion_producto, 'masa.nombre'))
                                    <span class="bw-pos-line-option"><x-heroicon-o-adjustments-horizontal class="h-3.5 w-3.5" /> Masa: {{ data_get($line->configuracion_producto, 'masa.nombre') }}</span>
                                @endif
                                <span>{{ $this->money($line->precio_unitario) }} c/u</span>
                            </div>
                            @if (! $this->isReadOnly)
                            <div class="bw-pos-line-controls">
                                <button type="button" wire:click="decrementLine({{ $line->getKey() }})" class="bw-pos-btn-step" aria-label="Disminuir">
                                    <x-heroicon-o-minus class="h-4 w-4" />
                                </button>
                                <span class="bw-pos-line-qty">{{ $line->cantidad }}</span>
                                <button type="button" wire:click="incrementLine({{ $line->getKey() }})" class="bw-pos-btn-step" aria-label="Aumentar">
                                    <x-heroicon-o-plus class="h-4 w-4" />
                                </button>
                                @if ($line->combo_id)
                                    <button type="button" wire:click="editCombo({{ $line->getKey() }})" class="bw-pos-btn-step is-edit" aria-label="Editar combo">
                                        <x-heroicon-o-pencil-square class="h-4 w-4" />
                                    </button>
                                @elseif ($line->producto_id && data_get($line->configuracion_producto, 'masa.codigo'))
                                    <button type="button" wire:click="editProductMasa({{ $line->getKey() }})" class="bw-pos-btn-step is-edit" aria-label="Editar masa">
                                        <x-heroicon-o-pencil-square class="h-4 w-4" />
                                    </button>
                                @endif
                                <button type="button" wire:click="removeLine({{ $line->getKey() }})" class="bw-pos-btn-step is-danger" aria-label="Eliminar">
                                    <x-heroicon-o-trash class="h-4 w-4" />
                                </button>
                            </div>
                            @endif
                            <b class="bw-pos-line-total">{{ $this->money((float) $line->precio_unitario * $line->cantidad) }}</b>
                        </div>
                    @empty
                        <div class="bw-pos-summary-empty">
                            <x-heroicon-o-shopping-bag class="h-8 w-8 opacity-40" />
                            <span>Toca los productos para agregarlos</span>
                        </div>
                    @endforelse

                    @if ($this->sentDetails->isNotEmpty())
                        <div class="bw-pos-sent-heading">Enviados a cocina</div>
                        @foreach ($this->sentDetails as $line)
                            <div wire:key="sent-{{ $line->getKey() }}" class="bw-pos-summary-line is-sent {{ $line->estado_linea === \App\Enums\EstadoLineaPedido::CANCELADA ? 'is-cancelled' : '' }}">
                                <div class="bw-pos-line-info">
                                    <strong>{{ $line->combo?->nombre ?? $line->producto?->nombre }}</strong>
                                    @if ($line->combo_id)
                                        <span>{{ $this->comboLineSummary($line) }}</span>
                                    @endif
                                    <span>Envío de cocina #{{ $line->tanda_id }} · {{ $line->tanda?->estado?->label() ?? $line->estado_linea?->label() }}</span>
                                </div>
                                <b class="bw-pos-line-quantity">×{{ $line->cantidad }}</b>
                                <b class="bw-pos-line-total">{{ $line->estado_linea === \App\Enums\EstadoLineaPedido::ACTIVA ? $this->money((float) $line->precio_unitario * $line->cantidad) : '$0.00' }}</b>
                            </div>
                        @endforeach
                    @endif
                </div>

                <!-- TOTAL GRANDE -->
                <div class="bw-pos-summary-total">
                    <span>TOTAL</span>
                    <strong>{{ $this->money($this->total) }}</strong>
                </div>

                <!-- CAJA DE COBRO RÁPIDO (FAST CASH / TARJETA) -->
                @if (! $this->isReadOnly)
                    @if ($this->isTableFlow)
                    <div class="bw-pos-table-flow-actions">
                        <div class="bw-pos-table-flow-note">
                            <x-heroicon-o-information-circle class="h-5 w-5" />
                            <span>Esta cuenta se cobra después del consumo. La mesa seguirá ocupada.</span>
                        </div>

                        @if ($this->pendingDetails->isNotEmpty())
                            <button type="button" wire:click="sendToKitchen" class="bw-pos-kitchen-submit">
                                <x-heroicon-o-printer class="h-5 w-5" />
                                <span>ENVIAR {{ $this->pendingDetails->sum('cantidad') }} A COCINA</span>
                            </button>
                        @elseif ($this->canRequestAccount)
                            <button type="button" wire:click="sendToCashRegister" class="bw-pos-charge-submit">
                                <x-heroicon-o-banknotes class="h-5 w-5" />
                                <span>SOLICITAR CUENTA {{ $this->money($this->total) }}</span>
                            </button>
                        @endif

                        <button type="button" wire:click="saveAndReturnTables" class="bw-pos-sub-action-btn">
                            <x-heroicon-o-arrow-left class="h-5 w-5" />
                            Guardar y volver a mesas
                        </button>
                    </div>
                    @else
                    <div class="bw-pos-fast-tender-box">
                        <!-- Pestañas de método de pago -->
                        <div class="bw-pos-tender-tabs">
                            <button type="button" wire:click="$set('metodoPago', 'EFECTIVO')" class="{{ $metodoPago === 'EFECTIVO' ? 'is-active' : '' }}">
                                <x-heroicon-o-banknotes class="h-5 w-5" aria-hidden="true" /> <span>Efectivo</span>
                            </button>
                            <button type="button" wire:click="$set('metodoPago', 'TARJETA')" class="{{ $metodoPago === 'TARJETA' ? 'is-active' : '' }}">
                                <x-heroicon-o-credit-card class="h-5 w-5" aria-hidden="true" /> <span>Tarjeta</span>
                            </button>
                        </div>

                        @if ($metodoPago === 'EFECTIVO')
                            <!-- Botonera Simétrica de 6 Teclas: 5 Billetes + 1 Manual (0ms Alpine) -->
                            <div class="bw-pos-quick-amounts" aria-label="Billetes rápidos">
                                @foreach ($this->montosRapidos as $monto)
                                    <button
                                        type="button"
                                        wire:click="fastCharge('{{ $monto }}')"
                                        class="bw-pos-quick-amount {{ $monto == number_format($this->total, 2, '.', '') ? 'is-exacto' : '' }}"
                                        title="Cobrar con {{ $this->simboloMoneda }}{{ $monto }}"
                                    >
                                        {{ $monto == number_format($this->total, 2, '.', '') ? 'Exacto ' . $this->money($this->total) : $this->simboloMoneda . $monto }}
                                    </button>
                                @endforeach
                                <button type="button" @click="openManual()" class="bw-pos-quick-amount is-manual" title="Digitar monto manual">
                                    <x-heroicon-o-pencil-square class="h-4 w-4" aria-hidden="true" /> <span>Manual</span>
                                </button>
                            </div>

                            @if ($this->change > 0)
                                <div class="bw-pos-change-banner">
                                    <span>VUELTO:</span>
                                    <strong>{{ $this->simboloMoneda }}{{ number_format($this->change, 2) }}</strong>
                                </div>
                            @endif
                        @else
                            <label class="bw-pos-toggle-card {{ $tarjetaAprobada ? 'is-on' : '' }}">
                                <input type="checkbox" wire:model.live="tarjetaAprobada">
                                <x-heroicon-o-credit-card class="h-5 w-5" />
                                <span>Datáfono aprobado</span>
                            </label>
                        @endif

                        <!-- Botón Principal de Cobro -->
                        <button
                            type="button"
                            wire:click="charge"
                            class="bw-pos-charge-submit"
                            @disabled(! $this->canSubmitPayment)
                        >
                            <x-heroicon-o-bolt class="h-5 w-5" />
                            <span>COBRAR {{ $this->money($this->total) }}</span>
                        </button>

                        @if ($this->isDeviceOrder)
                            <div class="bw-pos-order-sub-actions">
                                <button type="button" wire:click="sendToCashRegister" class="bw-pos-sub-action-btn">
                                    Enviar a caja
                                </button>
                            </div>
                        @endif
                    </div>
                    @endif
                @else
                    <div class="bw-pos-paid-status" role="status">
                        <x-heroicon-o-check-circle class="h-5 w-5" />
                        <span>{{ $pedido->estado_comercial?->value === 'PENDIENTE_COBRO' ? 'Enviado a caja' : 'Cobrado' }}</span>
                    </div>
                @endif
            </aside>
        </main>

        <!-- MODAL DE CAMBIO / VUELTO A ENTREGAR (CHANGE DUE MODAL CON COLORES OFICIALES) -->
        @if ($changeModalOpen)
            <div class="bw-pos-combo-modal" role="dialog" aria-modal="true" aria-labelledby="change-modal-title">
                <button type="button" class="bw-pos-combo-backdrop" wire:click="acceptChangeAndNext" aria-label="Aceptar y continuar"></button>
                <section class="bw-pos-change-dialog">
                    <div class="bw-pos-change-icon-circle">
                        <x-heroicon-o-check class="h-8 w-8 text-white" />
                    </div>

                    <span class="bw-pos-step-label"><x-heroicon-o-check class="h-4 w-4" aria-hidden="true" /> <span>Cobro registrado</span></span>
                    <h2 id="change-modal-title" class="bw-pos-change-ticket-title">{{ $lastCode }}</h2>

                    @if ($lastChange > 0)
                        <div class="bw-pos-change-hero-card">
                            <span class="bw-pos-change-hero-label">VUELTO A ENTREGAR</span>
                            <strong class="bw-pos-change-hero-val">{{ $this->simboloMoneda }}{{ number_format($lastChange, 2) }}</strong>
                        </div>
                    @else
                        <div class="bw-pos-change-hero-card is-exact">
                            <span class="bw-pos-change-hero-label">PAGO EXACTO</span>
                            <strong class="bw-pos-change-hero-val">Sin vuelto</strong>
                        </div>
                    @endif

                    <div class="bw-pos-change-receipt-grid">
                        <div class="bw-pos-change-receipt-item">
                            <span>Total cobrado</span>
                            <strong>{{ $this->simboloMoneda }}{{ number_format($lastTotal, 2) }}</strong>
                        </div>
                        <div class="bw-pos-change-receipt-item">
                            <span>Monto recibido</span>
                            <strong>{{ $this->simboloMoneda }}{{ number_format($lastReceived, 2) }}</strong>
                        </div>
                    </div>

                    <div class="bw-pos-change-chips">
                        <span class="bw-pos-change-chip"><x-heroicon-o-fire class="h-4 w-4" aria-hidden="true" /> <span>Comanda en cocina</span></span>
                        <span class="bw-pos-change-chip"><x-heroicon-o-receipt-percent class="h-4 w-4" aria-hidden="true" /> <span>Ticket emitido</span></span>
                    </div>

                    <button type="button" wire:click="acceptChangeAndNext" class="bw-pos-change-confirm-btn" autofocus>
                        <span>Aceptar y continuar</span>
                    </button>
                </section>
            </div>
        @endif

        <!-- MODAL DE TECLADO NUMÉRICO TÁCTIL PARA RECALL (#TICKET) - ALPINE 0ms -->
        <div x-show="recallOpen" x-cloak x-trap.noscroll="recallOpen" class="bw-pos-combo-modal" role="dialog" aria-modal="true" aria-labelledby="recall-modal-title">
            <button type="button" class="bw-pos-combo-backdrop" @click="closeRecall()" aria-label="Cerrar"></button>
            <section class="bw-pos-numpad-dialog">
                <header class="bw-pos-numpad-dialog-header">
                    <div>
                        <span class="bw-pos-step-label">RECALL DE COMANDAS MÓVILES</span>
                        <h2 id="recall-modal-title">Cargar Pedido o Mesa</h2>
                        <p>Digita el número de ticket (ej. 14) o número de mesa:</p>
                    </div>
                    <button type="button" @click="closeRecall()" class="bw-pos-dialog-close" aria-label="Cerrar">
                        <x-heroicon-o-x-mark class="h-6 w-6" />
                    </button>
                </header>

                <div class="bw-pos-numpad-display-box">
                    <span class="bw-pos-numpad-prefix">#</span>
                    <span class="bw-pos-numpad-value" x-text="recallDigits !== '' ? recallDigits : '0'"></span>
                </div>

                <div class="bw-pos-numpad-grid">
                    @foreach (['1', '2', '3', '4', '5', '6', '7', '8', '9'] as $d)
                        <button type="button" @click="pressRecall('{{ $d }}')" class="bw-pos-numpad-key">{{ $d }}</button>
                    @endforeach
                    <button type="button" @click="clearRecall()" class="bw-pos-numpad-key is-clear">C</button>
                    <button type="button" @click="pressRecall('0')" class="bw-pos-numpad-key">0</button>
                    <button type="button" @click="backspaceRecall()" class="bw-pos-numpad-key is-backspace"><x-heroicon-o-backspace class="h-5 w-5" aria-hidden="true" /></button>
                </div>

                <footer class="bw-pos-numpad-dialog-footer">
                    <button type="button" @click="closeRecall()" class="bw-pos-secondary-button">Cancelar</button>
                    <button type="button" @click="submitRecall()" class="bw-pos-primary-button" :disabled="recallDigits === ''">
                        <x-heroicon-o-bolt class="h-5 w-5" />
                        <span>Cargar Pedido</span>
                    </button>
                </footer>
            </section>
        </div>

        <!-- MODAL DE TECLADO NUMÉRICO TÁCTIL PARA EFECTIVO MANUAL - ALPINE 0ms -->
        <div x-show="manualOpen" x-cloak x-trap.noscroll="manualOpen" class="bw-pos-combo-modal" role="dialog" aria-modal="true" aria-labelledby="manual-amount-modal-title">
            <button type="button" class="bw-pos-combo-backdrop" @click="closeManual()" aria-label="Cerrar"></button>
            <section class="bw-pos-numpad-dialog">
                <header class="bw-pos-numpad-dialog-header">
                    <div>
                        <span class="bw-pos-step-label">MONTO RECIBIDO EN EFECTIVO</span>
                        <h2 id="manual-amount-modal-title">Ingresar Efectivo</h2>
                        <p>Total a cobrar: <strong>{{ $this->money($this->total) }}</strong></p>
                    </div>
                    <button type="button" @click="closeManual()" class="bw-pos-dialog-close" aria-label="Cerrar">
                        <x-heroicon-o-x-mark class="h-6 w-6" />
                    </button>
                </header>

                <div class="bw-pos-numpad-display-box">
                    <span class="bw-pos-numpad-prefix">{{ $this->simboloMoneda }}</span>
                    <span class="bw-pos-numpad-value" x-text="manualDigits !== '' ? manualDigits : '0.00'"></span>
                </div>

                <template x-if="calculatedChange > 0">
                    <div class="bw-pos-change-banner my-2">
                        <span>VUELTO A ENTREGAR:</span>
                        <strong>{{ $this->simboloMoneda }}<span x-text="calculatedChange"></span></strong>
                    </div>
                </template>

                <div class="bw-pos-numpad-grid">
                    @foreach (['1', '2', '3', '4', '5', '6', '7', '8', '9'] as $d)
                        <button type="button" @click="pressManual('{{ $d }}')" class="bw-pos-numpad-key">{{ $d }}</button>
                    @endforeach
                    <button type="button" @click="pressManual('.')" class="bw-pos-numpad-key font-bold">.</button>
                    <button type="button" @click="pressManual('0')" class="bw-pos-numpad-key">0</button>
                    <button type="button" @click="backspaceManual()" class="bw-pos-numpad-key is-backspace"><x-heroicon-o-backspace class="h-5 w-5" aria-hidden="true" /></button>
                </div>

                <footer class="bw-pos-numpad-dialog-footer">
                    <button type="button" @click="clearManual()" class="bw-pos-secondary-button">Limpiar</button>
                    <button type="button" @click="submitManual()" class="bw-pos-primary-button" :disabled="manualDigits === ''">
                        <x-heroicon-o-check class="h-5 w-5" />
                        <span>Cobrar Monto</span>
                    </button>
                </footer>
            </section>
        </div>

        @if ($masaModalOpen && $this->selectedMasaProduct)
            <div class="bw-pos-combo-modal" role="dialog" aria-modal="true" aria-labelledby="masa-modal-title">
                <button type="button" class="bw-pos-combo-backdrop" wire:click="closeMasaSelector" aria-label="Cerrar selector de masa"></button>
                <section class="bw-pos-combo-dialog bw-pos-masa-dialog">
                    <header class="bw-pos-combo-dialog-header">
                        <div>
                            <span class="bw-pos-step-label">CONFIGURAR PRODUCTO</span>
                            <h2 id="masa-modal-title">{{ $this->selectedMasaProduct->nombre }}</h2>
                            <p>Selecciona la masa antes de agregarlo al pedido.</p>
                        </div>
                        <button type="button" wire:click="closeMasaSelector" class="bw-pos-dialog-close" aria-label="Cerrar selector de masa">
                            <x-heroicon-o-x-mark class="h-5 w-5" />
                        </button>
                    </header>

                    <div class="bw-pos-masa-options" role="radiogroup" aria-label="Tipo de masa">
                        @foreach (\App\Enums\MasaPupusa::cases() as $masa)
                            <button
                                type="button"
                                wire:click="chooseMasa('{{ $masa->value }}')"
                                class="bw-pos-masa-option {{ $selectedMasa === $masa->value ? 'is-selected' : '' }}"
                                role="radio"
                                aria-checked="{{ $selectedMasa === $masa->value ? 'true' : 'false' }}"
                            >
                                <x-heroicon-o-squares-2x2 class="h-7 w-7" aria-hidden="true" />
                                <strong>{{ $masa->label() }}</strong>
                                <span>{{ $masa === \App\Enums\MasaPupusa::MAIZ ? 'Sabor tradicional' : 'Alternativa de arroz' }}</span>
                            </button>
                        @endforeach
                    </div>

                    <footer class="bw-pos-combo-dialog-footer">
                        <span class="bw-pos-masa-selection-label">
                            @if ($selectedMasa !== '')
                                Masa seleccionada: <strong>{{ \App\Enums\MasaPupusa::tryFrom($selectedMasa)?->label() ?? 'Sin definir' }}</strong>
                            @else
                                Selecciona una opción para continuar
                            @endif
                        </span>
                        <div>
                            <button type="button" wire:click="closeMasaSelector" class="bw-pos-secondary-button">Cancelar</button>
                            <button type="button" wire:click="saveProductWithMasa" class="bw-pos-primary-button" @disabled($selectedMasa === '')>
                                <x-heroicon-o-check class="h-5 w-5" />
                                Agregar producto
                            </button>
                        </div>
                    </footer>
                </section>
            </div>
        @endif
        <!-- MODAL DE CONFIGURACIÓN DE COMBOS (STEPPERS 38px) -->
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
                                        @php
                                            $storedSelection = $comboSelections[(string) $option->getKey()][(string) $product->getKey()] ?? 0;
                                            $selectedQuantity = is_array($storedSelection) ? collect($storedSelection)->sum() : (int) $storedSelection;
                                        @endphp
                                        <div class="bw-pos-combo-product-row {{ $product->requiere_masa ? 'has-mass-breakdown' : '' }}">
                                            <span>
                                                <strong>{{ $product->nombre }}</strong>
                                                <small>{{ $this->money($product->precio) }}</small>
                                            </span>
                                            @if ($product->requiere_masa)
                                                <div class="bw-pos-combo-mass-list" aria-label="Masa de {{ $product->nombre }}">
                                                    @foreach (\App\Enums\MasaPupusa::cases() as $masa)
                                                        @php $massQuantity = (int) data_get($storedSelection, $masa->value, 0); @endphp
                                                        <div class="bw-pos-combo-mass-row">
                                                            <strong>{{ $masa->label() }}</strong>
                                                            <div class="bw-pos-combo-stepper">
                                                                <button type="button" wire:click="changeComboSelectionMass({{ $option->getKey() }}, {{ $product->getKey() }}, '{{ $masa->value }}', -1)" @disabled($massQuantity < 1) class="bw-pos-btn-step-large" aria-label="Disminuir {{ $product->nombre }} de {{ $masa->label() }}">
                                                                    <x-heroicon-o-minus class="h-4 w-4" />
                                                                </button>
                                                                <b>{{ $massQuantity }}</b>
                                                                <button type="button" wire:click="changeComboSelectionMass({{ $option->getKey() }}, {{ $product->getKey() }}, '{{ $masa->value }}', 1)" @disabled($this->comboSelectionTotal($option->getKey()) >= $option->cantidad_requerida) class="bw-pos-btn-step-large" aria-label="Aumentar {{ $product->nombre }} de {{ $masa->label() }}">
                                                                    <x-heroicon-o-plus class="h-4 w-4" />
                                                                </button>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <div class="bw-pos-combo-stepper">
                                                    <button type="button" wire:click="changeComboSelection({{ $option->getKey() }}, {{ $product->getKey() }}, -1)" @disabled($selectedQuantity < 1) class="bw-pos-btn-step-large" aria-label="Disminuir {{ $product->nombre }}">
                                                        <x-heroicon-o-minus class="h-4 w-4" />
                                                    </button>
                                                    <b>{{ $selectedQuantity }}</b>
                                                    <button type="button" wire:click="changeComboSelection({{ $option->getKey() }}, {{ $product->getKey() }}, 1)" @disabled($this->comboSelectionTotal($option->getKey()) >= $option->cantidad_requerida) class="bw-pos-btn-step-large" aria-label="Aumentar {{ $product->nombre }}">
                                                        <x-heroicon-o-plus class="h-4 w-4" />
                                                    </button>
                                                </div>
                                            @endif
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

        <!-- MODAL DE ASIGNACIÓN DE MESA -->
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
