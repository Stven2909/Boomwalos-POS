@props([
    'centerLabel' => null,
    'rightLabel' => 'CAJA 1 · TURNO ACTIVO',
    'backUrl' => null,
    'backLabel' => 'Servicio',
])

<header class="bw-pos-header">
    <div class="bw-pos-header-brand">
        @if ($backUrl)
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

    <div class="bw-pos-header-session">
        <span>{{ $rightLabel }}</span>
        <span class="bw-pos-session-dot" aria-hidden="true"></span>
    </div>
</header>
