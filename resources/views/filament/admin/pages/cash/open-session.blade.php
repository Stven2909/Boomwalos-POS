<x-filament-panels::page>
    <div class="bw-pos-page bw-cash-opening-page">
        @include('filament.admin.components.pos-header', [
            'rightLabel' => 'CAJA 1 · TURNO INACTIVO',
        ])

        <main class="bw-cash-opening-main">
            <section class="bw-cash-opening-card" aria-labelledby="cash-opening-title">
                <span class="bw-pos-step-label">ANTES DE EMPEZAR</span>
                <h1 id="cash-opening-title">Abre el turno de caja</h1>
                <p>Necesitas un turno activo para registrar pedidos y mantener el control del efectivo.</p>

                <form wire:submit="openSession" class="bw-cash-opening-form">
                    <label for="montoInicial">Monto inicial</label>
                    <div class="bw-cash-amount-field">
                        <span>$</span>
                        <input id="montoInicial" type="number" min="0" step="0.01" wire:model="montoInicial" inputmode="decimal" autocomplete="off">
                    </div>
                    @error('montoInicial')
                        <span class="bw-pos-feedback is-error" role="alert">{{ $message }}</span>
                    @enderror

                    <button type="submit" class="bw-pos-primary-button">
                        Abrir turno
                        <x-heroicon-o-arrow-right class="h-5 w-5" />
                    </button>
                </form>

                <a href="{{ \App\Filament\Pages\Dashboard::getUrl() }}" class="bw-pos-secondary-button">Volver al inicio</a>
            </section>
        </main>
    </div>
</x-filament-panels::page>
