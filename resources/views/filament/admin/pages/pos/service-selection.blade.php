<x-filament-panels::page>
    <div class="bw-pos-page bw-pos-service-page">
        @include('filament.admin.components.pos-header', [
            'rightLabel' => $this->actorName() . ' · CAJA 1',
        ])

        <main class="bw-pos-service-main">
            <section class="bw-pos-service-intro" aria-labelledby="service-title">
                <span class="bw-pos-step-label">PUNTO DE VENTA · MOSTRADOR</span>
                <h1 id="service-title">¿Qué quieres hacer?</h1>
                <p>Toca una opción para continuar</p>
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

            <section class="bw-pos-service-options bw-pos-service-options-five" aria-label="Acciones del punto de venta">
                <button type="button" wire:click="startNewOrder" class="bw-pos-service-card">
                    <span class="bw-pos-service-icon" aria-hidden="true">
                        <x-heroicon-o-plus-circle class="h-9 w-9" />
                    </span>
                    <strong>Nuevo pedido</strong>
                    <span>Abre el catálogo directamente</span>
                </button>

                <button type="button" wire:click="openOrderSearch" class="bw-pos-service-card">
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

                <button type="button" wire:click="openTables" class="bw-pos-service-card">
                    <span class="bw-pos-service-icon" aria-hidden="true">
                        <x-heroicon-o-table-cells class="h-9 w-9" />
                    </span>
                    <strong>Mesas</strong>
                    <span>Asignar mesas del local</span>
                </button>

                <button type="button" wire:click="openPendingList" class="bw-pos-service-card">
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

                <button type="button" wire:click="openCashState" class="bw-pos-service-card">
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
