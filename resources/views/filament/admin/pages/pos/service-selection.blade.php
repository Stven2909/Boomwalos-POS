<x-filament-panels::page>
    <div class="bw-pos-page bw-pos-service-page">
        @include('filament.admin.components.pos-header', [
            'centerLabel' => $this->operationContext(),
            'rightLabel' => $this->actorName(),
            'showGavetaButton' => true,
        ])

        <main class="bw-pos-service-main">
            <section class="bw-pos-service-intro" aria-labelledby="service-title">
                <span class="bw-pos-step-label">PUNTO DE VENTA</span>
                <h1 id="service-title">¿Cómo será este pedido?</h1>
                <p>Selecciona el flujo autorizado para esta sucursal.</p>
            </section>

            @foreach ($this->cashAlerts as $alert)
                <div class="bw-pos-cash-alert is-{{ $alert['tipo'] }}" role="{{ $alert['tipo'] === 'error' ? 'alert' : 'warning' }}">
                    <x-heroicon-o-exclamation-triangle class="h-5 w-5" />
                    <div>
                        <strong>{{ $alert['titulo'] }}</strong>
                        <span>{{ $alert['mensaje'] }}</span>
                    </div>
                </div>
            @endforeach

            <section class="bw-pos-sales-summary" aria-label="Resumen de ventas">
                <div class="bw-pos-sales-stat">
                    <span>Ventas del turno</span>
                    <strong>
                        @if ($this->turnoSales === null)
                            —
                        @else
                            {{ $this->simboloMoneda }}{{ number_format((float) $this->turnoSales, 2, '.', ',') }}
                        @endif
                    </strong>
                </div>
                <div class="bw-pos-sales-stat">
                    <span>Ventas del día</span>
                    <strong>{{ $this->simboloMoneda }}{{ number_format((float) $this->daySales, 2, '.', ',') }}</strong>
                </div>
            </section>

            <section
                class="bw-pos-service-options bw-pos-service-options-five {{ ($this->flowSettings->mostradorPrepago xor $this->flowSettings->mesaPostpago) ? 'has-single-flow' : '' }}"
                aria-label="Acciones del punto de venta"
            >
                @if ($this->flowSettings->mostradorPrepago)
                <button type="button" wire:click="startNewOrder" class="bw-pos-service-card bw-pos-service-primary bw-pos-service-flow bw-pos-service-flow-counter {{ $this->flowSettings->predeterminado === \App\Enums\FlujoPos::MOSTRADOR_PREPAGO ? 'is-default' : '' }}">
                    <span class="bw-pos-service-icon" aria-hidden="true">
                        <x-heroicon-o-plus-circle class="h-9 w-9" />
                    </span>
                    <strong>Mostrador</strong>
                    <span>Tomar pedido · cobrar ahora · enviar comanda</span>
                </button>
                @endif

                <button type="button" wire:click="openOrderSearch" class="bw-pos-service-card bw-pos-service-secondary bw-pos-service-utility bw-pos-service-search">
                    <span class="bw-pos-service-icon" aria-hidden="true">
                        <x-heroicon-o-magnifying-glass class="h-9 w-9" />
                    </span>
                    <strong>Consultar pedido</strong>
                    <span>
                        {{ $this->openCount }} abiertos hoy
                        @if ($this->openCount > 0)
                            <b class="bw-pos-pending-badge">{{ $this->openCount }}</b>
                        @endif
                    </span>
                </button>

                @if ($this->flowSettings->mesaPostpago)
                <button type="button" wire:click="openTables" class="bw-pos-service-card bw-pos-service-primary bw-pos-service-flow bw-pos-service-flow-table {{ $this->flowSettings->predeterminado === \App\Enums\FlujoPos::MESA_POSTPAGO ? 'is-default' : '' }}">
                    <span class="bw-pos-service-icon" aria-hidden="true">
                        <x-heroicon-o-table-cells class="h-9 w-9" />
                    </span>
                    <strong>Comer aquí</strong>
                    <span>Tomar pedido · enviar a cocina · cobrar después</span>
                </button>
                @endif

                <button type="button" wire:click="openPendingList" class="bw-pos-service-card bw-pos-service-secondary bw-pos-service-utility bw-pos-service-pending">
                    <span class="bw-pos-service-icon" aria-hidden="true">
                        <x-heroicon-o-banknotes class="h-9 w-9" />
                    </span>
                    <strong>Pedidos por cobrar</strong>
                    <span>
                        {{ $this->pendingCount }} en caja
                        @if ($this->pendingCount > 0)
                            <b class="bw-pos-pending-badge">{{ $this->pendingCount }}</b>
                        @endif
                    </span>
                </button>

                <button type="button" wire:click="openCashState" class="bw-pos-service-card bw-pos-service-secondary bw-pos-service-utility bw-pos-service-cash">
                    <span class="bw-pos-service-icon" aria-hidden="true">
                        <x-heroicon-o-wallet class="h-9 w-9" />
                    </span>
                    <strong>Estado de caja</strong>
                    <span>
                        @if ($this->cashState)
                            Turno activo · abierta {{ $this->cashState['fecha_apertura']?->format('H:i') }}
                        @else
                            Sin turno activo · abrir caja
                        @endif
                    </span>
                </button>
            </section>

            @if ($feedback)
                <div class="bw-pos-feedback is-success" role="status">
                    <x-heroicon-o-check-circle class="h-5 w-5" />
                    <span>{{ $feedback }}</span>
                </div>
            @endif

            <footer class="bw-pos-service-footer">
                <p>Accesible para táctil · zonas de toque amplias · alto contraste</p>
                <a href="{{ \App\Filament\Pages\Dashboard::getUrl() }}" class="bw-pos-secondary-button">
                    <x-heroicon-o-x-mark class="h-5 w-5" />
                    Cancelar
                </a>
            </footer>
        </main>
    </div>
</x-filament-panels::page>
