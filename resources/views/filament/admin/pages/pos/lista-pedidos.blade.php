<x-filament-panels::page>
    <div class="bw-pos-page bw-pos-orders-page">
        @include('filament.admin.components.pos-header', [
            'backUrl' => \App\Filament\Pages\Pos\ServiceSelection::getUrl(),
            'backLabel' => 'Servicio',
            'rightLabel' => $this->actorName() . ' · PEDIDOS POR COBRAR',
        ])

        <main class="bw-pos-service-main bw-pos-orders-main">
            <section class="bw-pos-service-intro bw-pos-page-intro" aria-labelledby="orders-title">
                <span class="bw-pos-step-label">CAJA · COBROS PENDIENTES</span>
                <h1 id="orders-title">Pedidos por cobrar</h1>
                <p>Cuentas enviadas a caja. Toca una para registrar el pago.</p>
            </section>

            <section class="bw-pos-orders-list" aria-label="Pedidos pendientes de cobro">
                @forelse ($this->pendingOrders as $order)
                    <button type="button" wire:click="openOrder({{ $order->getKey() }})" class="bw-pos-order-card">
                        <span class="bw-pos-order-code">
                            <strong>{{ $order->codigoCortoLabel() ?: 'PEDIDO' }}</strong>
                            <small>{{ $this->orderContextLabel($order) }}</small>
                        </span>
                        <span class="bw-pos-order-meta">
                            <span>{{ $this->orderItemCount($order) }} ítems</span>
                            <span>{{ $order->numero_seguimiento }}</span>
                        </span>
                        <span class="bw-pos-order-amount">
                            <strong>{{ $this->money($this->orderTotal($order)) }}</strong>
                            <small>COBRAR</small>
                        </span>
                    </button>
                @empty
                    <div class="bw-pos-empty-state">
                        <x-heroicon-o-inbox-stack class="h-8 w-8" />
                        <strong>No hay pedidos pendientes de cobro.</strong>
                        <span>Cuando un dispositivo envíe una cuenta a caja, aparecerá aquí.</span>
                    </div>
                @endforelse
            </section>

            <footer class="bw-pos-service-footer">
                <a href="{{ \App\Filament\Pages\Pos\ServiceSelection::getUrl() }}" class="bw-pos-secondary-button">
                    <x-heroicon-o-arrow-left class="h-5 w-5" />
                    Volver
                </a>
            </footer>
        </main>
    </div>
</x-filament-panels::page>
