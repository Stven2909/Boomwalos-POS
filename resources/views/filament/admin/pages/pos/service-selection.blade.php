<x-filament-panels::page>
    <div class="bw-pos-page bw-pos-service-page">
        @include('filament.admin.components.pos-header', [
            'rightLabel' => $this->actorName() . ' · CAJA 1 · TURNO ACTIVO',
        ])

        <main class="bw-pos-service-main">
            <section class="bw-pos-service-intro" aria-labelledby="service-title">
                <span class="bw-pos-step-label">PASO 1 DE 5 · REGISTRAR PEDIDO</span>
                <h1 id="service-title">¿Cómo será este pedido?</h1>
                <p>Toca una opción para continuar</p>
            </section>

            <section class="bw-pos-service-options" aria-label="Tipo de pedido">
                <button type="button" wire:click="selectLocal" class="bw-pos-service-card is-selected">
                    <span class="bw-pos-service-icon" aria-hidden="true">
                        <x-heroicon-o-table-cells class="h-9 w-9" />
                    </span>
                    <strong>En el local</strong>
                    <span>Asignar mesa</span>
                </button>

                <button type="button" wire:click="selectTakeaway" class="bw-pos-service-card">
                    <span class="bw-pos-service-icon" aria-hidden="true">
                        <x-heroicon-o-shopping-bag class="h-9 w-9" />
                    </span>
                    <strong>Para llevar</strong>
                    <span>Retiro en mostrador</span>
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

