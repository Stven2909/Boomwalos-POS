<x-filament-panels::page>
    <div class="bw-pos-page bw-cash-closing-page">
        @include('filament.admin.components.pos-header', [
            'rightLabel' => 'CAJA 1 · TURNO ACTIVO',
        ])

        <main class="bw-cash-opening-main">
            <section class="bw-cash-opening-card" aria-labelledby="cash-closing-title">
                <span class="bw-pos-step-label">FIN DEL TURNO</span>
                <h1 id="cash-closing-title">Cierra el turno de caja</h1>
                <p>Registra el arqueo físico. El sistema compara con el efectivo esperado y guarda la diferencia.</p>

                @if ($feedback)
                    <div class="bw-pos-feedback is-error" role="alert">{{ $feedback }}</div>
                @endif

                <div class="bw-cash-summary-row">
                    <span>Efectivo esperado (sistema)</span>
                    <strong>{{ $this->money($this->efectivoEsperado) }}</strong>
                </div>

                <form wire:submit="closeSession" class="bw-cash-opening-form">
                    <label for="efectivoContado">Efectivo contado</label>
                    <div class="bw-cash-amount-field">
                        <span>$</span>
                        <input id="efectivoContado" type="number" min="0" step="0.01" wire:model="efectivoContado" inputmode="decimal" autocomplete="off">
                    </div>
                    @error('efectivoContado')
                        <span class="bw-pos-feedback is-error" role="alert">{{ $message }}</span>
                    @enderror

                    <div class="bw-cash-summary-row bw-cash-summary-row--difference">
                        <span>Diferencia</span>
                        <strong>{{ $this->money($this->diferencia) }}</strong>
                    </div>

                    <button type="submit" class="bw-pos-primary-button">
                        Cerrar turno
                        <x-heroicon-o-check class="h-5 w-5" />
                    </button>
                </form>

                <a href="{{ \App\Filament\Pages\Dashboard::getUrl() }}" class="bw-pos-secondary-button">Volver al inicio</a>
            </section>
        </main>
    </div>
</x-filament-panels::page>
