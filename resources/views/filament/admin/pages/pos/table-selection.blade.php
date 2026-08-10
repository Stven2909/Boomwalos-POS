<x-filament-panels::page>
    <div class="bw-pos-page bw-pos-table-page">
        @include('filament.admin.components.pos-header', [
            'backUrl' => $entryMode === 'mesas'
                ? \App\Filament\Pages\Dashboard::getUrl()
                : \App\Filament\Pages\Pos\ServiceSelection::getUrl(),
            'backLabel' => $entryMode === 'mesas' ? 'Dashboard' : 'Servicio',
            'rightLabel' => $entryMode === 'mesas' ? 'MESAS · OPERACIÓN' : 'PASO 2 DE 5 · ELEGIR MESA',
        ])

        <main class="bw-pos-table-main">
            <section class="bw-pos-page-intro" aria-labelledby="table-title">
                <span class="bw-pos-step-label">{{ $entryMode === 'mesas' ? 'MÓDULO MESAS' : 'PASO 2 DE 5 · ELEGIR MESA' }}</span>
                <h1 id="table-title">Selecciona una mesa</h1>
                <p>Verde disponible · morado cuenta abierta · ámbar cobrado pendiente de entrega</p>
            </section>

            <nav class="bw-pos-zone-tabs" aria-label="Zonas del establecimiento">
                @foreach (\App\Enums\ZonaMesa::cases() as $zone)
                    <button
                        type="button"
                        wire:click="setZone('{{ $zone->value }}')"
                        class="bw-pos-zone-tab {{ $zona === $zone->value ? 'is-active' : '' }}"
                        aria-pressed="{{ $zona === $zone->value ? 'true' : 'false' }}"
                    >
                        {{ $zone->label() }}
                    </button>
                @endforeach
            </nav>

            <section class="bw-pos-table-grid" aria-label="Mesas disponibles">
                @forelse ($this->tables as $mesa)
                    @php
                        $activeOrder = $mesa->pedidos->first();
                        $isOccupied = $mesa->estado === \App\Enums\EstadoMesa::OCUPADA;
                        $isPaid = $activeOrder?->estado_comercial === \App\Enums\EstadoComercialPedido::COBRADO;
                        $isSelected = $selectedMesaId === $mesa->getKey();
                    @endphp
                    <button
                        type="button"
                        wire:click="selectTable({{ $mesa->getKey() }})"
                        class="bw-pos-table-node {{ $isPaid ? 'is-paid' : ($isOccupied ? 'is-occupied' : ($isSelected ? 'is-selected' : 'is-free')) }}"
                        aria-label="Mesa {{ $mesa->numero }}, {{ $isPaid ? 'cobrado pendiente de entrega' : ($isOccupied ? 'cuenta abierta' : 'libre') }}"
                    >
                        <span class="bw-pos-table-figure" aria-hidden="true">
                            <span class="bw-pos-table-surface">
                                <x-heroicon-o-table-cells class="h-9 w-9" />
                            </span>
                            <span class="bw-pos-table-leg bw-pos-table-leg-left"></span>
                            <span class="bw-pos-table-leg bw-pos-table-leg-right"></span>
                        </span>
                        <strong class="bw-pos-table-number">MESA {{ str_pad($mesa->numero, 2, '0', STR_PAD_LEFT) }}</strong>
                        <span class="bw-pos-table-state">{{ $isPaid ? 'Cobrado · pendiente de entrega' : ($isOccupied ? 'Cuenta abierta' : 'Libre') }}</span>
                        @if ($isSelected)
                            <span class="bw-pos-selected-mark" aria-hidden="true">
                                <x-heroicon-o-check class="h-4 w-4" />
                            </span>
                        @endif
                    </button>
                @empty
                    <div class="bw-pos-empty-state">
                        <x-heroicon-o-table-cells class="h-8 w-8" />
                        <strong>No hay mesas configuradas en esta zona.</strong>
                        <span>El administrador puede agregarlas desde la configuración del establecimiento.</span>
                    </div>
                @endforelse
            </section>

            @error('mesa')
                <p class="bw-pos-feedback is-error" role="alert">{{ $message }}</p>
            @enderror

            @if ($entryMode !== 'mesas')
            <div class="bw-pos-table-actions">
                <button
                    type="button"
                    wire:click="continueWithTable"
                    class="bw-pos-primary-button"
                    @disabled(! $selectedMesaId)
                >
                    Continuar {{ $selectedMesaId ? 'con Mesa ' . str_pad((string) $selectedMesaNumero, 2, '0', STR_PAD_LEFT) : '' }}
                    <x-heroicon-o-arrow-right class="h-5 w-5" />
                </button>
            </div>
            @endif
        </main>
    </div>
</x-filament-panels::page>
