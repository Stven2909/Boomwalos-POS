<x-filament-panels::page>
    <div class="bw-pos-page bw-cash-closing-page">
        @include('filament.admin.components.pos-header', [
            'rightLabel' => $this->hasActiveSession ? 'CAJA 1 · TURNO ACTIVO' : 'CAJA 1 · TURNO CERRADO',
        ])

        <main class="bw-cash-opening-main">
            <section class="bw-cash-opening-card" aria-labelledby="cash-closing-title">
                <span class="bw-pos-step-label">FIN DEL TURNO</span>
                <h1 id="cash-closing-title">Cierra el turno de caja</h1>
                <p>Registra el arqueo físico. El sistema compara con el efectivo esperado y guarda la diferencia.</p>

                @if (session('turno_cerrado'))
                    <div class="bw-pos-feedback" role="status">El turno de caja ya está cerrado.</div>
                @endif

                @if ($feedback)
                    <div class="bw-pos-feedback is-error" role="alert">{{ $feedback }}</div>
                @endif

                @if ($this->hasActiveSession)
                    <div class="bw-cash-summary-row">
                        <span>Monto inicial</span>
                        <strong>{{ $this->money($this->resumen['monto_inicial']) }}</strong>
                    </div>
                    <div class="bw-cash-summary-row">
                        <span>Ventas en efectivo</span>
                        <strong>{{ $this->money($this->resumen['total_efectivo']) }}</strong>
                    </div>
                    <div class="bw-cash-summary-row">
                        <span>Ventas con tarjeta</span>
                        <strong>{{ $this->money($this->resumen['total_tarjeta']) }}</strong>
                    </div>
                    <div class="bw-cash-summary-row">
                        <span>Total de ventas</span>
                        <strong>{{ $this->money($this->resumen['total_ventas']) }}</strong>
                    </div>
                    <div class="bw-cash-summary-row">
                        <span>Efectivo esperado (sistema)</span>
                        <strong>{{ $this->money($this->efectivoEsperado) }}</strong>
                    </div>

                    <form wire:submit="closeSession" class="bw-cash-opening-form">
                        <label for="efectivoContado">Efectivo contado</label>
                        <div class="bw-cash-amount-field">
                            <span>$</span>
                            <input id="efectivoContado" type="number" min="0" step="0.01" wire:model.live="efectivoContado" inputmode="decimal" autocomplete="off">
                        </div>
                        @error('efectivoContado')
                            <span class="bw-pos-feedback is-error" role="alert">{{ $message }}</span>
                        @enderror

                        <div class="bw-cash-summary-row bw-cash-summary-row--difference">
                            <span>Diferencia</span>
                            <strong>{{ $this->money($this->diferencia) }}</strong>
                        </div>

                        <button
                            type="submit"
                            wire:confirm="¿Seguro que quieres cerrar el turno? Se cerrará tu sesión."
                            wire:loading.attr="disabled"
                            class="bw-pos-primary-button"
                        >
                            <span wire:loading.remove>Firmar y cerrar turno</span>
                            <span wire:loading>Cerrando turno…</span>
                            <x-heroicon-o-check class="h-5 w-5" />
                        </button>
                    </form>
                @else
                    <div class="bw-pos-read-only-state">
                        <span>No hay un turno de caja abierto en este momento.</span>
                    </div>
                    <a href="{{ \App\Filament\Pages\Dashboard::getUrl() }}" class="bw-pos-secondary-button">Volver al inicio</a>
                @endif
            </section>
        </main>
    </div>
</x-filament-panels::page>
