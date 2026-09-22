@props([
    'centerLabel' => null,
    'rightLabel' => 'OPERACIÓN DEL POS',
    'backUrl' => null,
    'backAction' => null,
    'backLabel' => 'Servicio',
    'showGavetaButton' => false,
])

<header class="bw-pos-header">
    <div class="bw-pos-header-brand">
        @if ($backAction)
            <button type="button" wire:click="{{ $backAction }}" class="bw-pos-back-link">
                <x-heroicon-o-arrow-left class="h-5 w-5" />
                <span>{{ $backLabel }}</span>
            </button>
        @elseif ($backUrl)
            <a href="{{ $backUrl }}" class="bw-pos-back-link">
                <x-heroicon-o-arrow-left class="h-5 w-5" />
                <span>{{ $backLabel }}</span>
            </a>
        @else
            <div class="bw-pos-brand" aria-label="{{ $posBranding->displayName() }}">
                <img src="{{ $posBranding->logoUrl() }}" alt="" class="bw-pos-logo">
                <span>{{ mb_strtoupper($posBranding->displayName()) }}</span>
            </div>
        @endif
    </div>

    @if ($centerLabel)
        <div class="bw-pos-header-context">
            <x-heroicon-o-table-cells class="h-5 w-5" />
            <span>{{ $centerLabel }}</span>
        </div>
    @endif

    <div class="bw-pos-header-right">
        @if ($showGavetaButton)
            <button
                type="button"
                wire:click="abrirGaveta"
                class="bw-pos-gaveta-button"
                title="Abrir gaveta de dinero"
                aria-label="Abrir gaveta de dinero"
            >
                <x-heroicon-o-banknotes class="h-6 w-6" />
            </button>
        @endif

        <div class="bw-pos-header-session">
            <span>{{ $rightLabel }}</span>
            <span class="bw-pos-session-dot" aria-hidden="true"></span>
        </div>
    </div>
</header>
