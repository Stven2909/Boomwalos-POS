<x-filament-panels::page>
    @include('filament.admin.components.brand-css')
    <div
        class="bw-kds-page"
        wire:poll.5s="refreshBoard"
        x-data="{
            soundEnabled: false,
            clock: '',
            now: Date.now(),
            lastPollAt: Date.now(),
            audioContext: null,
            init() {
                this.updateClock();
                window.setInterval(() => {
                    this.updateClock();
                    this.now = Date.now();
                }, 1000);
            },
            updateClock() {
                this.clock = new Intl.DateTimeFormat('es-SV', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true,
                }).format(new Date());
            },
            toggleSound() {
                this.soundEnabled = ! this.soundEnabled;
                if (this.soundEnabled) {
                    this.playTone();
                }
            },
            playTone() {
                if (! this.soundEnabled) {
                    return;
                }

                this.audioContext ??= new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = this.audioContext.createOscillator();
                const gain = this.audioContext.createGain();
                oscillator.type = 'sine';
                oscillator.frequency.value = 760;
                gain.gain.setValueAtTime(0.0001, this.audioContext.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.18, this.audioContext.currentTime + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, this.audioContext.currentTime + 0.22);
                oscillator.connect(gain);
                gain.connect(this.audioContext.destination);
                oscillator.start();
                oscillator.stop(this.audioContext.currentTime + 0.24);
            },
        }"
        @kds-board-updated.window="lastPollAt = Date.now()"
        @kds-new-tanda.window="playTone()"
    >
        <header class="bw-kds-header">
            <div class="bw-kds-brand">
                <img src="{{ $posBranding->logoUrl() }}" alt="" class="bw-kds-logo">
                <div>
                    <strong>{{ mb_strtoupper($posBranding->displayName()) }}</strong>
                    <span>COCINA · KDS</span>
                </div>
            </div>

            <div class="bw-kds-header-status">
                <span class="bw-kds-live-indicator">
                    <i aria-hidden="true"></i>
                    SERVICIO EN VIVO
                </span>
                <span class="bw-kds-clock" x-text="clock"></span>
            </div>
        </header>

        <main class="bw-kds-main">
            <div class="bw-kds-toolbar">
                <div class="bw-kds-filter-group" aria-label="Filtrar tandas">
                    @foreach ($this->filterOptions() as $option)
                        <button
                            type="button"
                            class="bw-kds-filter {{ $filter === $option['value'] ? 'is-active' : '' }}"
                            wire:click="setFilter('{{ $option['value'] }}')"
                            aria-pressed="{{ $filter === $option['value'] ? 'true' : 'false' }}"
                        >
                            <span>{{ $option['label'] }}</span>
                            <b>{{ $this->filterCount($option['value']) }}</b>
                        </button>
                    @endforeach
                </div>

                <div class="bw-kds-toolbar-actions">
                    <span class="bw-kds-connection" x-show="now - lastPollAt > 15000" x-cloak>
                        <i aria-hidden="true"></i>
                        Sin conexión
                    </span>
                    <span class="bw-kds-updated">Actualizado {{ $lastUpdatedAt }}</span>
                    <button
                        type="button"
                        class="bw-kds-sound"
                        x-on:click="toggleSound()"
                        :aria-pressed="soundEnabled.toString()"
                    >
                        <x-heroicon-o-speaker-wave class="h-4 w-4" x-show="soundEnabled" />
                        <x-heroicon-o-speaker-x-mark class="h-4 w-4" x-show="! soundEnabled" />
                        <span x-text="soundEnabled ? 'Sonido activo' : 'Activar sonido'"></span>
                    </button>
                </div>
            </div>

            @if ($feedback)
                <div class="bw-kds-feedback" role="status" aria-live="polite">
                    <x-heroicon-o-information-circle class="h-5 w-5" />
                    <span>{{ $feedback }}</span>
                </div>
            @endif

            <div class="bw-kds-column-tabs" role="tablist" aria-label="Estados de cocina">
                @foreach ($this->columns() as $column)
                    @php $tabStatus = $column['status']->value; @endphp
                    <button
                        type="button"
                        class="bw-kds-column-tab bw-kds-column-tab-{{ $column['tone'] }} {{ $activeStatus === $tabStatus ? 'is-active' : '' }}"
                        wire:click="setActiveStatus('{{ $tabStatus }}')"
                        role="tab"
                        aria-selected="{{ $activeStatus === $tabStatus ? 'true' : 'false' }}"
                    >
                        <span>{{ $column['title'] }}</span>
                        <b>{{ $this->statusCounts()[$tabStatus] ?? 0 }}</b>
                    </button>
                @endforeach
            </div>

            <div class="bw-kds-columns">
                @foreach ($this->columns() as $column)
                    @php $status = $column['status']->value; $columnBatches = $this->batchesByStatus()->get($status, collect()); @endphp
                    <section
                        class="bw-kds-column {{ $activeStatus === $status ? 'is-selected' : '' }}"
                        data-tone="{{ $column['tone'] }}"
                        aria-labelledby="kds-column-{{ $status }}"
                    >
                        <header class="bw-kds-column-header">
                            <div>
                                <span class="bw-kds-column-kicker">{{ $column['tone'] === 'pending' ? 'ENTRADA' : 'FLUJO' }}</span>
                                <h2 id="kds-column-{{ $status }}">{{ $column['title'] }}</h2>
                            </div>
                            <span class="bw-kds-column-count">{{ $this->statusCounts()[$status] ?? 0 }}</span>
                        </header>

                        <div class="bw-kds-column-list">
                            @forelse ($columnBatches as $batch)
                                @php
                                    $activeLines = $batch->detalles->where('estado_linea', \App\Enums\EstadoLineaPedido::ACTIVA);
                                    $cancelledLines = $batch->detalles->where('estado_linea', \App\Enums\EstadoLineaPedido::CANCELADA);
                                @endphp
                                <article class="bw-kds-ticket" wire:key="kds-ticket-{{ $batch->getKey() }}">
                                    <div class="bw-kds-ticket-topline">
                                        <div>
                                            <strong>{{ $batch->pedido?->numero_seguimiento }}</strong>
                                            <span>{{ $this->locationLabel($batch) }}</span>
                                        </div>
                                        <span class="bw-kds-ticket-time bw-kds-ticket-time-{{ $this->elapsedTone($batch) }}">
                                            {{ $this->elapsedLabel($batch) }}
                                        </span>
                                    </div>

                                    <div class="bw-kds-ticket-meta">
                                        <span>Tanda {{ $batch->numero_tanda }}</span>
                                        <span>{{ $this->zoneLabel($batch) }}</span>
                                        <span class="bw-kds-commercial-state">{{ $batch->pedido?->estado_comercial?->label() }}</span>
                                    </div>

                                    <ul class="bw-kds-ticket-lines">
                                        @foreach ($activeLines as $line)
                                            <li>
                                                <div class="bw-kds-line-heading">
                                                    <strong>{{ $line->cantidad }} × {{ $line->combo?->nombre ?? $line->producto?->nombre ?? 'Producto' }}</strong>
                                                </div>

                                                @if ($line->combo_id && $line->seleccion_combo)
                                                    <ul class="bw-kds-combo-breakdown">
                                                        @foreach ($this->comboLineSummary($line->seleccion_combo) as $selectionLine)
                                                            <li>{{ $selectionLine }}</li>
                                                        @endforeach
                                                    </ul>
                                                @endif

                                                @foreach ($line->detallePedidoNotas as $detailNote)
                                                    @if ($detailNote->notaCocina?->nombre)
                                                        <p class="bw-kds-note">{{ $detailNote->notaCocina->nombre }}</p>
                                                    @endif
                                                @endforeach
                                            </li>
                                        @endforeach

                                        @foreach ($cancelledLines as $line)
                                            <li class="bw-kds-cancelled-line">
                                                <span>{{ $line->cantidad }} × {{ $line->combo?->nombre ?? $line->producto?->nombre ?? 'Producto' }}</span>
                                                <small>Cancelada</small>
                                            </li>
                                        @endforeach
                                    </ul>

                                    <button
                                        type="button"
                                        class="bw-kds-ticket-action bw-kds-ticket-action-{{ $column['tone'] }}"
                                        wire:click="{{ $column['action'] }}({{ $batch->getKey() }})"
                                        wire:loading.attr="disabled"
                                        wire:target="{{ $column['action'] }}({{ $batch->getKey() }})"
                                        @if ($column['status'] === \App\Enums\EstadoCocina::LISTA)
                                            wire:confirm="¿Confirmar que esta tanda fue entregada?"
                                        @endif
                                    >
                                        @if ($column['status'] === \App\Enums\EstadoCocina::PENDIENTE)
                                            <x-heroicon-o-play class="h-5 w-5" />
                                        @elseif ($column['status'] === \App\Enums\EstadoCocina::EN_PREPARACION)
                                            <x-heroicon-o-check class="h-5 w-5" />
                                        @else
                                            <x-heroicon-o-hand-thumb-up class="h-5 w-5" />
                                        @endif
                                        {{ $column['actionLabel'] }}
                                    </button>
                                </article>
                            @empty
                                <div class="bw-kds-empty-state">
                                    <x-heroicon-o-check-circle class="h-8 w-8" />
                                    <strong>Sin tandas en esta columna</strong>
                                    <span>El tablero se actualiza automáticamente.</span>
                                </div>
                            @endforelse
                        </div>
                    </section>
                @endforeach
            </div>
        </main>
    </div>
</x-filament-panels::page>
