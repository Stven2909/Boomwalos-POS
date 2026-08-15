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
                        La cuenta no tiene productos activos para cobrar.
                    </div>
                @else
                    <div class="bw-pos-charge-note">
                        <x-heroicon-o-fire class="h-5 w-5" />
                        <span>Al confirmar el pago, los productos pendientes se envían a cocina.</span>
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

                    <div class="bw-pos-numpad" aria-label="Teclado numérico">
                        @foreach (['7','8','9','4','5','6','1','2','3'] as $digito)
                            <button type="button" wire:click="ingresarDigito('{{ $digito }}')" class="bw-pos-numpad-key">{{ $digito }}</button>
                        @endforeach
                        <button type="button" wire:click="limpiarMonto" class="bw-pos-numpad-key is-utility">C</button>
                        <button type="button" wire:click="ingresarDigito('0')" class="bw-pos-numpad-key">0</button>
                        <button type="button" wire:click="ingresarDigito('.')" class="bw-pos-numpad-key">.</button>
                        <button type="button" wire:click="borrarDigito" class="bw-pos-numpad-key is-utility" aria-label="Borrar último dígito">
                            <x-heroicon-o-backspace class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="bw-pos-change-row">
                        <span>Cambio</span>
                        <strong>{{ $this->money($this->change) }}</strong>
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

                    <label class="bw-pos-payment-field">
                        <span>Referencia de la transacción</span>
                        <input type="text" wire:model="tarjetaReferencia" maxlength="100" placeholder="Ej. 123456" inputmode="numeric">
                    </label>

                    <label class="bw-pos-payment-field">
                        <span>Terminal (opcional)</span>
                        <input type="text" wire:model="tarjetaTerminal" maxlength="100" placeholder="Ej. DAT-01">
                    </label>
                @endif

                <button type="button" wire:click="charge" class="bw-pos-charge-button" @disabled(! $this->canSubmitPayment)>
                    <x-heroicon-o-check-circle class="h-5 w-5" />
                    Cobrar y enviar a cocina
                </button>

                <a href="{{ $this->backUrl() }}" class="bw-pos-secondary-button bw-pos-charge-back">
                    {{ $pedido->origen_pedido?->value === \App\Enums\OrigenPedido::DISPOSITIVO->value ? 'Volver a pendientes' : 'Volver al pedido' }}
                </a>
            </aside>
        </main>
    </div>
</x-filament-panels::page>

