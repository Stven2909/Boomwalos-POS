<x-filament-panels::page>
    <div class="bw-pos-page bw-pos-charge-page">
        @include('filament.admin.components.pos-header', [
            'backUrl' => $this->backUrl(),
            'backLabel' => $pedido->origen_pedido?->value === \App\Enums\OrigenPedido::DISPOSITIVO->value ? 'Pendientes' : 'Pedido',
            'centerLabel' => $pedido->mesa
                ? 'MESA ' . $pedido->mesa->numero . ' · COBRO'
                : 'PARA LLEVAR · COBRO',
            'rightLabel' => 'PASO 4 DE 5 · COBRAR',
        ])

        <main class="bw-pos-charge-main">
            <section class="bw-pos-charge-content" aria-labelledby="charge-title">
                <div class="bw-pos-charge-heading">
                    <div>
                        <span class="bw-pos-step-label">COBRO DE CUENTA</span>
                        <h1 id="charge-title">Cobrar cuenta</h1>
                        <p>
                            {{ $pedido->mesa ? 'Mesa ' . $pedido->mesa->numero : 'Pedido para llevar' }}
                            @if ($pedido->codigoCortoLabel())
                                · {{ $pedido->codigoCortoLabel() }}
                            @endif
                        </p>
                    </div>
                    <span class="bw-pos-order-tracking">{{ $pedido->numero_seguimiento }}</span>
                </div>

                <div class="bw-pos-charge-lines" aria-label="Productos de la cuenta">
                    @forelse ($this->activeDetails as $line)
                        <article class="bw-pos-charge-line">
                            <div>
                                <strong>{{ $line->combo?->nombre ?? $line->producto?->nombre }}</strong>
                                @if ($line->combo_id)
                                    <span>{{ $this->comboLineSummary($line) }}</span>
                                @endif
                                <small>{{ $line->cantidad }} × {{ $this->money($line->precio_unitario) }}</small>
                            </div>
                            <b>{{ $this->money((float) $line->precio_unitario * $line->cantidad) }}</b>
                        </article>
                    @empty
                        <div class="bw-pos-empty-state">
                            <x-heroicon-o-receipt-percent class="h-8 w-8" />
                            <strong>La cuenta no tiene productos activos.</strong>
                        </div>
                    @endforelse
                </div>

                @if (! $this->isReadyToCharge)
                    <div class="bw-pos-feedback is-error" role="alert">
                        <x-heroicon-o-exclamation-triangle class="h-5 w-5" />
                        Envía a cocina todos los productos pendientes antes de cobrar.
                    </div>
                @endif
            </section>

            <aside class="bw-pos-charge-panel" aria-labelledby="payment-title">
                <div>
                    <span class="bw-pos-step-label">RESUMEN DE PAGO</span>
                    <h2 id="payment-title">Total a cobrar</h2>
                </div>

                <div class="bw-pos-charge-total">
                    <span>Total</span>
                    <strong>{{ $this->money($this->total) }}</strong>
                </div>

                @if ($feedback)
                    <div class="bw-pos-feedback is-error" role="alert">{{ $feedback }}</div>
                @endif

                <fieldset class="bw-pos-payment-methods">
                    <legend>Método de pago</legend>
                    @foreach (\App\Enums\MetodoPago::cases() as $method)
                        <label class="bw-pos-payment-method {{ $metodoPago === $method->value ? 'is-selected' : '' }}">
                            <input type="radio" wire:model.live="metodoPago" value="{{ $method->value }}">
                            <span>
                                @if ($method === \App\Enums\MetodoPago::EFECTIVO)
                                    <x-heroicon-o-banknotes class="h-5 w-5" />
                                @else
                                    <x-heroicon-o-credit-card class="h-5 w-5" />
                                @endif
                                {{ $method->label() }}
                            </span>
                        </label>
                    @endforeach
                </fieldset>

                @if ($metodoPago === \App\Enums\MetodoPago::EFECTIVO->value)
                    <label class="bw-pos-payment-field">
                        <span>Monto recibido</span>
                        <div>
                            <span>$</span>
                            <input type="number" min="0" step="0.01" wire:model.live="montoRecibido" inputmode="decimal" placeholder="0.00">
                        </div>
                    </label>

                    <div class="bw-pos-change-row">
                        <span>Cambio</span>
                        <strong>{{ $this->money($this->change) }}</strong>
                    </div>
                @else
                    <div class="bw-pos-card-note">
                        <x-heroicon-o-information-circle class="h-5 w-5" />
                        <span>Se cobrará el total exacto con tarjeta.</span>
                    </div>
                @endif

                <button type="button" wire:click="charge" class="bw-pos-charge-button" @disabled(! $this->isReadyToCharge)>
                    <x-heroicon-o-check-circle class="h-5 w-5" />
                    Confirmar pago
                </button>

                <a href="{{ $this->backUrl() }}" class="bw-pos-secondary-button bw-pos-charge-back">
                    {{ $pedido->origen_pedido?->value === \App\Enums\OrigenPedido::DISPOSITIVO->value ? 'Volver a pendientes' : 'Volver al pedido' }}
                </a>
            </aside>
        </main>
    </div>
</x-filament-panels::page>
