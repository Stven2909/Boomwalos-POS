<x-filament-panels::page>
    <div class="bw-pos-page bw-pos-entrega-page">
        <main class="bw-pos-entrega-main">
            <section class="bw-pos-entrega-heading" aria-labelledby="entrega-title">
                <div>
                    <span class="bw-pos-step-label">ENTREGA · EXPO</span>
                    <h1 id="entrega-title">Pedidos listos para entregar</h1>
                    <p>Marca "Entregado" cuando el cliente reciba su pedido.</p>
                </div>
                <span class="bw-pos-entrega-total">
                    {{ $this->readyBatches->count() }} listos
                </span>
            </section>

            @if ($feedback)
                <div class="bw-pos-feedback" role="status">{{ $feedback }}</div>
            @endif

            <section class="bw-pos-entrega-ready" aria-label="Pedidos listos">
                @forelse ($this->readyBatches as $tanda)
                    <article class="bw-pos-entrega-card">
                        <header>
                            <div class="bw-pos-entrega-code">
                                <strong>{{ $tanda->pedido?->codigoCortoLabel() ?: 'PEDIDO' }}</strong>
                                <span>{{ $this->locationLabel($tanda) }}</span>
                            </div>
                            <span class="bw-pos-entrega-time">{{ $this->elapsedLabel($tanda) }}</span>
                        </header>

                        <div class="bw-pos-entrega-lines">
                            @foreach ($tanda->detalles as $line)
                                <div class="bw-pos-entrega-line">
                                    <b>{{ $line->cantidad }} ×</b>
                                    <span>
                                        <strong>{{ $line->combo?->nombre ?? $line->producto?->nombre }}</strong>
                                        @if ($line->combo_id)
                                            <small>{{ implode(', ', $this->comboLineSummary($line->seleccion_combo ?? [])) }}</small>
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>

                        <button
                            type="button"
                            wire:click="markDelivered({{ $tanda->getKey() }})"
                            class="bw-pos-entrega-deliver"
                        >
                            <x-heroicon-o-check-circle class="h-5 w-5" />
                            Entregado
                        </button>
                    </article>
                @empty
                    <div class="bw-pos-empty-state">
                        <x-heroicon-o-check-badge class="h-8 w-8" />
                        <strong>No hay pedidos listos en este momento.</strong>
                        <span>Los pedidos marcados "Listo" en cocina aparecerán aquí.</span>
                    </div>
                @endforelse
            </section>

            @if ($this->preparingBatches->isNotEmpty())
                <section class="bw-pos-entrega-preparing" aria-label="En preparación">
                    <h2>En preparación</h2>
                    <div class="bw-pos-entrega-preparing-list">
                        @foreach ($this->preparingBatches as $tanda)
                            <span>
                                <b>{{ $tanda->pedido?->codigoCortoLabel() ?: 'PEDIDO' }}</b>
                                {{ $this->locationLabel($tanda) }}
                                <small>{{ $tanda->detalles->sum('cantidad') }} ítems</small>
                            </span>
                        @endforeach
                    </div>
                </section>
            @endif
        </main>
    </div>
</x-filament-panels::page>
