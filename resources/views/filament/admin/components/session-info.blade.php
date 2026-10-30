@php
    $context = app(\App\Contracts\EstablishmentContextInterface::class);
    $establishment = $context->currentOrNull();
    $canSwitchEstablishment = $context->accessible()->count() > 1;
    $hasActiveSession = $establishment && \App\Models\SesionCaja::query()
        ->where('establecimiento_id', $establishment->getKey())
        ->whereNull('fecha_cierre')
        ->exists();
    $canClose = auth()->user()?->can('cerrar_caja') ?? false;
@endphp

@if ($canSwitchEstablishment)
    <a
        href="{{ \App\Filament\Pages\EstablishmentSelection::getUrl() }}"
        class="hidden items-center gap-1.5 rounded-full bg-[var(--bw-primary)]/10 px-3 py-1.5 text-xs font-semibold text-[var(--bw-primary)] hover:bg-[var(--bw-primary)]/15 lg:inline-flex"
        title="Cambiar sucursal"
    >
        <x-heroicon-o-building-storefront class="h-4 w-4" />
        <span class="max-w-40 truncate">{{ $establishment?->nombre ?? 'Seleccionar sucursal' }}</span>
        <x-heroicon-m-chevron-down class="h-3.5 w-3.5" />
    </a>
@elseif ($establishment)
    <span class="hidden items-center gap-1.5 rounded-full bg-[var(--bw-primary)]/10 px-3 py-1.5 text-xs font-semibold text-[var(--bw-primary)] lg:inline-flex">
        <x-heroicon-o-building-storefront class="h-4 w-4" />
        <span class="max-w-40 truncate">{{ $establishment->nombre }}</span>
    </span>
@endif

@if ($hasActiveSession && $canClose)
    <a
        href="{{ \App\Filament\Pages\Cash\CloseSession::getUrl() }}"
        class="hidden items-center gap-1.5 rounded-full bg-[var(--bw-primary)]/10 px-3 py-1.5 text-xs font-semibold text-[var(--bw-primary)] hover:bg-[var(--bw-primary)]/15 lg:inline-flex"
        title="Cerrar turno de caja"
    >
        <x-heroicon-o-banknotes class="h-4 w-4" />
        Caja 1 · Cerrar
    </a>
@else
    <span class="hidden items-center gap-1.5 rounded-full bg-[var(--bw-primary)]/10 px-3 py-1.5 text-xs font-semibold text-[var(--bw-primary)] lg:inline-flex">
        <x-heroicon-o-banknotes class="h-4 w-4" />
        Caja 1
    </span>
@endif
