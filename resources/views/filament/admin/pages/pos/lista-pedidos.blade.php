<x-filament-panels::page>
    <div class="bw-pos-page bw-pos-orders-page">
        @include('filament.admin.components.pos-header', [
            'backUrl' => \App\Filament\Pages\Pos\ServiceSelection::getUrl(),
            'backLabel' => 'Inicio',
            'rightLabel' => $this->actorName() . ' · CONSULTA DE PEDIDOS',
        ])

        <main class="bw-pos-service-main bw-pos-orders-main">
            <section class="bw-pos-service-intro bw-pos-page-intro" aria-labelledby="orders-title">
                <span class="bw-pos-step-label">CAJA · CONSULTA DE PEDIDOS</span>
                <h1 id="orders-title">Pedidos del Turno</h1>
                <p>Revisa pedidos activos, pendientes de cobro y reimprime tickets del turno.</p>
            </section>

            <nav class="bw-pos-zone-tabs" aria-label="Filtros de pedidos">
                <button
                    type="button"
                    wire:click="setFiltro('abiertos')"
                    class="bw-pos-zone-tab {{ $filtro === 'abiertos' ? 'is-active' : '' }}"
                    aria-pressed="{{ $filtro === 'abiertos' ? 'true' : 'false' }}"
                >
                    Abiertos
                </button>
                <button
                    type="button"
                    wire:click="setFiltro('pendientes')"
                    class="bw-pos-zone-tab {{ $filtro === 'pendientes' ? 'is-active' : '' }}"
                    aria-pressed="{{ $filtro === 'pendientes' ? 'true' : 'false' }}"
                >
                    Por cobrar
                </button>
                <button
                    type="button"
                    wire:click="setFiltro('todos')"
                    class="bw-pos-zone-tab {{ $filtro === 'todos' ? 'is-active' : '' }}"
                    aria-pressed="{{ $filtro === 'todos' ? 'true' : 'false' }}"
                >
                    Todos
                </button>
            </nav>

            <label class="bw-pos-search-field bw-pos-orders-search">
                <x-heroicon-o-magnifying-glass class="h-5 w-5" />
                <input
                    type="search"
                    wire:model.live.debounce.250ms="search"
                    placeholder="Buscar por #código, mesa, seguimiento u origen"
                    aria-label="Buscar pedidos"
                >
            </label>

            @if ($feedback)
                <div class="bw-pos-feedback" role="status">{{ $feedback }}</div>
            @endif

            <section class="bw-pos-orders-list" aria-label="Pedidos del día">
                @forelse ($this->orders as $order)
                    @php
                        $orderStateClass = match ($order->estado_comercial?->value) {
                            'ABIERTO' => 'is-open',
                            'PENDIENTE_COBRO' => 'is-pending',
                            'COBRADO' => 'is-paid',
                            'CERRADO' => 'is-closed',
                            'CANCELADO' => 'is-cancelled',
                            default => 'is-neutral',
                        };
                    @endphp
                    <article wire:key="order-{{ $order->getKey() }}" class="bw-pos-order-card">
                        <span class="bw-pos-order-code">
                            <strong>{{ $order->codigoCortoLabel() ?: 'PEDIDO' }}</strong>
                            <small>{{ $this->orderContextLabel($order) }} · {{ $this->timeLabel($order) }}</small>
                        </span>
                        <span class="bw-pos-order-meta">
                            <span>{{ $order->origen_pedido?->label() }}</span>
                            <span>{{ $this->orderItemCount($order) }} ítems</span>
                            <span class="bw-pos-order-state {{ $orderStateClass }}">
                                {{ $this->estadoLabel($order) }}
                            </span>
                        </span>
                        <span class="bw-pos-order-amount">
                            <strong>{{ $this->money($this->orderTotal($order)) }}</strong>
                            <span class="bw-pos-order-actions">
                                @if ($order->estado_comercial?->isPayable() || $order->estado_comercial === \App\Enums\EstadoComercialPedido::ABIERTO)
                                    <button type="button" wire:click="openOrder({{ $order->getKey() }})" class="bw-pos-action-button is-primary">
                                        <x-heroicon-o-bolt class="h-4 w-4" />
                                        Abrir Pedido
                                    </button>
                                @endif
                                @if (in_array($order->estado_comercial, [\App\Enums\EstadoComercialPedido::COBRADO, \App\Enums\EstadoComercialPedido::CERRADO], true))
                                    <button
                                        type="button"
                                        wire:click="reimprimirTicket({{ $order->getKey() }})"
                                        class="bw-pos-action-button"
                                        title="Reimprimir ticket de cliente"
                                    >
                                        <x-heroicon-o-printer class="h-4 w-4" />
                                        Reimprimir
                                    </button>
                                @endif
                                @can('cancelar_pedido')
                                    <button
                                        type="button"
                                        wire:click="cancelarPedido({{ $order->getKey() }})"
                                        wire:confirm="¿Cancelar este pedido? Se registrará en auditoría y la mesa quedará libre."
                                        class="bw-pos-action-button is-danger"
                                    >
                                        <x-heroicon-o-x-mark class="h-4 w-4" />
                                        Cancelar
                                    </button>
                                @endcan
                            </span>
                        </span>
                    </article>
                @empty
                    <div class="bw-pos-empty-state">
                        <x-heroicon-o-inbox-stack class="h-8 w-8" />
                        <strong>No hay pedidos en esta vista.</strong>
                        <span>Cuando un dispositivo envíe una cuenta a caja o se abra un pedido, aparecerá aquí.</span>
                    </div>
                @endforelse
            </section>

            @if ($this->orders->hasPages())
                <nav class="bw-pos-pagination" aria-label="Paginación de pedidos">
                    {{ $this->orders->links() }}
                </nav>
            @endif

            <footer class="bw-pos-service-footer">
                <a href="{{ \App\Filament\Pages\Pos\ServiceSelection::getUrl() }}" class="bw-pos-secondary-button">
                    <x-heroicon-o-arrow-left class="h-5 w-5" />
                    Volver
                </a>
            </footer>
        </main>
    </div>
</x-filament-panels::page>
