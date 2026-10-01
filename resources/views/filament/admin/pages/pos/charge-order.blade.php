<x-filament-panels::page>
    <div class="bw-pos-page bw-pos-charge-page" x-data="{ manualOpen: false }" @keydown.window.escape="manualOpen = false">
        @include('filament.admin.components.pos-header', [
            'backUrl' => $this->backUrl(),
            'backLabel' => $pedido->origen_pedido?->value === \App\Enums\OrigenPedido::DISPOSITIVO->value ? 'Pendientes' : 'Pedido',
            'centerLabel' => $pedido->mesa
                ? 'MESA ' . $pedido->mesa->numero . ' · COBRO'
                : 'PARA LLEVAR · COBRO',
            'rightLabel' => 'PASO 4 DE 5 · COBRAR',
            'showGavetaButton' => true,
        ])

        <main class="bw-pos-charge-main">
            <section class="bw-pos-charge-content" aria-labelledby="charge-title">
                <div class="bw-pos-charge-heading">
                    <div>
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
                                @if (data_get($line->configuracion_producto, 'masa.nombre'))
                                    <span>Masa: {{ data_get($line->configuracion_producto, 'masa.nombre') }}</span>
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
                        La cuenta no tiene productos activos para cobrar.
                    </div>
                @else
                    <div class="bw-pos-charge-note {{ $this->hasPendingKitchenLines ? 'is-warning' : 'is-ready' }}">
                        @if ($this->hasPendingKitchenLines)
                            <x-heroicon-o-printer class="h-5 w-5" />
                            <span>Al cobrar, solo los productos pendientes se enviarán a cocina.</span>
                        @else
                            <x-heroicon-o-check-circle class="h-5 w-5" />
                            <span>La comanda ya fue enviada. El cobro no repetirá productos en cocina.</span>
                        @endif
                    </div>
                @endif
            </section>

            <aside class="bw-pos-charge-panel" aria-labelledby="payment-title">
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
                    <div class="bw-pos-cash-flow">
                        <div class="bw-pos-payment-field">
                            <span>Monto recibido</span>
                            <div class="bw-pos-amount-display">
                                <span>{{ $this->simboloMoneda }}</span>
                                <strong>{{ $montoRecibido === '' ? '0.00' : $montoRecibido }}</strong>
                            </div>
                        </div>

                        <div class="bw-pos-quick-amounts" aria-label="Montos rápidos">
                            @foreach ($this->montosRapidos as $monto)
                                <button type="button" wire:click="usarMontoRapido('{{ $monto }}')" class="bw-pos-quick-amount">
                                    {{ $this->simboloMoneda }}{{ number_format((float) $monto, 2) }}
                                </button>
                            @endforeach
                            <button type="button" wire:click="usarMontoExacto" class="bw-pos-quick-amount is-exacto">
                                Exacto
                            </button>
                        </div>

                        <button type="button" @click="manualOpen = true; $wire.limpiarMonto()" class="bw-pos-quick-amount is-manual">
                            <x-heroicon-o-pencil-square class="h-5 w-5" /> Manual
                        </button>

                        <div class="bw-pos-change-row">
                            <span>Cambio</span>
                            <strong>{{ $this->money($this->change) }}</strong>
                        </div>
                    </div>
                @else
                    <div class="bw-pos-card-note">
                        <x-heroicon-o-information-circle class="h-5 w-5" />
                        <span>Se cobrará el total exacto con tarjeta.</span>
                    </div>

                    <label class="bw-pos-toggle {{ $tarjetaAprobada ? 'is-on' : '' }}">
                        <input type="checkbox" wire:model.live="tarjetaAprobada">
                        <span class="bw-pos-toggle-track" aria-hidden="true"></span>
                        <span>El datáfono aprobó el pago</span>
                    </label>
                @endif

                <button type="button" wire:click="charge" class="bw-pos-charge-button" @disabled(! $this->canSubmitPayment)>
                    <x-heroicon-o-check-circle class="h-5 w-5" />
                    Cobrar {{ $this->money($this->total) }}
                </button>

                <a href="{{ $this->backUrl() }}" class="bw-pos-secondary-button bw-pos-charge-back">
                    {{ $pedido->origen_pedido?->value === \App\Enums\OrigenPedido::DISPOSITIVO->value ? 'Volver a pendientes' : 'Volver al pedido' }}
                </a>
            </aside>
        </main>
        <div x-show="manualOpen" x-cloak x-trap.noscroll="manualOpen" class="bw-pos-combo-modal" role="dialog" aria-modal="true" aria-labelledby="manual-amount-modal-title">
            <button type="button" class="bw-pos-combo-backdrop" @click="manualOpen = false" aria-label="Cerrar"></button>
            <section class="bw-pos-numpad-dialog">
                <header class="bw-pos-numpad-dialog-header">
                    <div>
                        <span class="bw-pos-step-label">MONTO RECIBIDO EN EFECTIVO</span>
                        <h2 id="manual-amount-modal-title">Ingresar Efectivo</h2>
                        <p>Total a cobrar: <strong>{{ $this->money($this->total) }}</strong></p>
                    </div>
                    <button type="button" @click="manualOpen = false" class="bw-pos-dialog-close" aria-label="Cerrar">
                        <x-heroicon-o-x-mark class="h-6 w-6" />
                    </button>
                </header>

                <div class="bw-pos-numpad-display-box">
                    <span class="bw-pos-numpad-prefix">{{ $this->simboloMoneda }}</span>
                    <span class="bw-pos-numpad-value">{{ $montoRecibido === '' ? '0.00' : number_format((float) $montoRecibido, 2, '.', '') }}</span>
                </div>

                @if ($feedback)
                    <div class="bw-pos-feedback is-error" role="alert">{{ $feedback }}</div>
                @endif
                <div class="bw-pos-change-row"><span>Cambio</span><strong>{{ $this->money($this->change) }}</strong></div>

                <div class="bw-pos-numpad-grid">
                    @foreach (['1', '2', '3', '4', '5', '6', '7', '8', '9'] as $d)
                        <button type="button" wire:click="ingresarDigito('{{ $d }}')" class="bw-pos-numpad-key">{{ $d }}</button>
                    @endforeach
                    <button type="button" wire:click="ingresarDigito('.')" class="bw-pos-numpad-key font-bold">.</button>
                    <button type="button" wire:click="ingresarDigito('0')" class="bw-pos-numpad-key">0</button>
                    <button type="button" wire:click="borrarDigito" class="bw-pos-numpad-key is-backspace"><x-heroicon-o-backspace class="h-5 w-5" aria-hidden="true" /></button>
                </div>

                <footer class="bw-pos-numpad-dialog-footer">
                    <button type="button" wire:click="limpiarMonto" class="bw-pos-secondary-button">Limpiar</button>
                    <button type="button" wire:click="charge" class="bw-pos-primary-button" @disabled($montoRecibido === '' || (float) $montoRecibido < $this->total)>
                        <x-heroicon-o-check class="h-5 w-5" />
                        <span>Cobrar Monto</span>
                    </button>
                </footer>
            </section>
        </div>

    </div>
</x-filament-panels::page>
