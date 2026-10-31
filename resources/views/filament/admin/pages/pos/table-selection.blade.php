<x-filament-panels::page>
    <div class="bw-pos-page bw-pos-table-page">
        @include('filament.admin.components.pos-header', [
            'backUrl' => $entryMode === 'mesas'
                ? \App\Filament\Pages\Dashboard::getUrl()
                : \App\Filament\Pages\Pos\ServiceSelection::getUrl(),
            'backLabel' => $entryMode === 'mesas' ? 'Dashboard' : 'Servicio',
            'centerLabel' => $this->operationContext(),
            'rightLabel' => $this->actorName() . ($this->esLlevar ? ' · LLEVAR' : ' · MESAS'),
            'showGavetaButton' => true,
        ])

        <main class="bw-pos-table-main">
            <section class="bw-pos-page-intro" aria-labelledby="table-title">
                <span class="bw-pos-step-label">
                    {{ $this->esLlevar
                        ? 'MOSTRADOR · PARA LLEVAR'
                        : ($entryMode === 'mesas' ? 'MÓDULO MESAS' : 'PASO 2 DE 5 · ELEGIR MESA') }}
                </span>
                <h1 id="table-title">{{ $this->esLlevar ? 'Pedidos para llevar' : 'Selecciona una mesa' }}</h1>
                <p>
                    {{ $this->esLlevar
                        ? 'Abre un pedido nuevo o retoma uno abierto. Se cobra antes de entregar y se identifica con su número corto.'
                        : 'Verde disponible · morado cuenta abierta · ámbar cobrado pendiente de entrega' }}
                </p>
            </section>

            @if (count($this->availableZones) > 1)
            <nav class="bw-pos-zone-tabs" aria-label="Ubicaciones del establecimiento">
                @foreach ($this->availableZones as $zone)
                    <button
                        type="button"
                        wire:click="setZone('{{ $zone['nombre'] }}')"
                        class="bw-pos-zone-tab {{ $zona === $zone['nombre'] ? 'is-active' : '' }}"
                        aria-pressed="{{ $zona === $zone['nombre'] ? 'true' : 'false' }}"
                    >
                        {{ $zone['etiqueta'] }}
                    </button>
                @endforeach
            </nav>
            @endif

            @if ($this->esLlevar)
                <section class="bw-pos-takeaway-board" aria-label="Pedidos para llevar">
                    <div class="bw-pos-takeaway-head">
                        <div>
                            <span class="bw-pos-step-label">COBRO INMEDIATO</span>
                            <h2>Para llevar</h2>
                            <p>El pedido se cobra ahora y se entrega con su número corto.</p>
                        </div>

                        <button type="button" wire:click="startTakeawayOrder" class="bw-pos-primary-button bw-pos-takeaway-new">
                            <x-heroicon-o-plus-circle class="h-5 w-5" />
                            Nuevo pedido para llevar
                        </button>
                    </div>

                    @error('llevar')
                        <p class="bw-pos-feedback is-error" role="alert">{{ $message }}</p>
                    @enderror

                    <div class="bw-pos-takeaway-grid">
                        @forelse ($this->takeawayOrders as $order)
                            <button
                                type="button"
                                wire:click="selectTakeawayOrder({{ $order->getKey() }})"
                                class="bw-pos-takeaway-card is-{{ strtolower($order->estado_comercial->value) }}"
                            >
                                <span class="bw-pos-takeaway-code">{{ $order->codigoCortoLabel() }}</span>
                                <strong class="bw-pos-takeaway-customer">{{ $order->nombreClienteLabel() }}</strong>
                                <span class="bw-pos-takeaway-total">{{ $this->money($order->total()) }}</span>
                                <span class="bw-pos-takeaway-meta">
                                    <span class="bw-pos-takeaway-state">{{ $order->estado_comercial->label() }}</span>
                                    <span class="bw-pos-takeaway-time">{{ $order->created_at?->format('H:i') }}</span>
                                </span>
                            </button>
                        @empty
                            <div class="bw-pos-empty-state">
                                <x-heroicon-o-shopping-bag class="h-8 w-8" />
                                <strong>No hay pedidos para llevar abiertos.</strong>
                                <span>Abre uno nuevo con el botón de arriba.</span>
                            </div>
                        @endforelse
                    </div>
                </section>
            @else
                <div class="bw-pos-state-legend" aria-label="Estados de mesa">
                    <span><i class="is-free" aria-hidden="true"></i>Libre</span>
                    <span><i class="is-occupied" aria-hidden="true"></i>Cuenta abierta</span>
                    <span><i class="is-paid" aria-hidden="true"></i>Cobrado · pendiente</span>
                </div>

                <section class="bw-pos-table-grid" aria-label="Mesas disponibles">
                    @forelse ($this->tables as $mesa)
                        @php
                            $activeOrder = $mesa->pedidos->first();
                            $isOccupied = $mesa->estado === \App\Enums\EstadoMesa::OCUPADA;
                            $isPaid = $activeOrder?->estado_comercial === \App\Enums\EstadoComercialPedido::COBRADO;
                            $isSelected = $selectedMesaId === $mesa->getKey();
                        @endphp
                        <button
                            type="button"
                            wire:click="selectTable({{ $mesa->getKey() }})"
                            class="bw-pos-table-node {{ $isPaid ? 'is-paid' : ($isOccupied ? 'is-occupied' : ($isSelected ? 'is-selected' : 'is-free')) }}"
                            aria-label="Mesa {{ $mesa->numero }}, {{ $isPaid ? 'cobrado pendiente de entrega' : ($isOccupied ? 'cuenta abierta' : 'libre') }}"
                        >
                            <span class="bw-pos-table-figure" aria-hidden="true">
                                <span class="bw-pos-table-surface">
                                    <x-heroicon-o-table-cells class="h-9 w-9" />
                                </span>
                                <span class="bw-pos-table-leg bw-pos-table-leg-left"></span>
                                <span class="bw-pos-table-leg bw-pos-table-leg-right"></span>
                            </span>
                            <strong class="bw-pos-table-number">MESA {{ str_pad($mesa->numero, 2, '0', STR_PAD_LEFT) }}</strong>
                            <span class="bw-pos-table-state">{{ $isPaid ? 'Cobrado · pendiente de entrega' : ($isOccupied ? 'Cuenta abierta' : 'Libre') }}</span>
                            @if ($isOccupied && filled($activeOrder?->nombre_cliente))
                                <span class="bw-pos-table-customer">
                                    <x-heroicon-o-user class="h-3.5 w-3.5" aria-hidden="true" />
                                    {{ $activeOrder->nombre_cliente }}
                                </span>
                            @endif
                            @if ($isSelected)
                                <span class="bw-pos-selected-mark" aria-hidden="true">
                                    <x-heroicon-o-check class="h-4 w-4" />
                                </span>
                            @endif
                        </button>
                    @empty
                        <div class="bw-pos-empty-state">
                            <x-heroicon-o-table-cells class="h-8 w-8" />
                            <strong>No hay mesas configuradas en esta ubicación.</strong>
                            <span>El administrador puede agregarlas desde Operación → Mesas.</span>
                        </div>
                    @endforelse
                </section>

                @error('mesa')
                    <p class="bw-pos-feedback is-error" role="alert">{{ $message }}</p>
                @enderror

                @if ($entryMode !== 'mesas')
                <div class="bw-pos-table-actions">
                    <button
                        type="button"
                        wire:click="continueWithTable"
                        class="bw-pos-primary-button"
                        @disabled(! $selectedMesaId)
                    >
                        Continuar {{ $selectedMesaId ? 'con Mesa ' . str_pad((string) $selectedMesaNumero, 2, '0', STR_PAD_LEFT) : '' }}
                        <x-heroicon-o-arrow-right class="h-5 w-5" />
                    </button>
                </div>
                @endif
            @endif
        </main>
    </div>
</x-filament-panels::page>
