@php
    try {
        $establishmentId = app(\App\Contracts\EstablishmentContextInterface::class)->id();
    } catch (\Throwable) {
        $establishmentId = null;
    }
    $hasActiveSession = $establishmentId && \App\Models\SesionCaja::query()
        ->where('establecimiento_id', $establishmentId)
        ->whereNull('fecha_cierre')
        ->exists();
    $canClose = auth()->user()?->can('cerrar_caja') ?? false;
@endphp

@if ($hasActiveSession && $canClose)
    <a
        href="{{ \App\Filament\Pages\Cash\CloseSession::getUrl() }}"
        class="hidden items-center gap-1.5 rounded-full bg-[#6B4E63]/10 px-3 py-1.5 text-xs font-semibold text-[#6B4E63] hover:bg-[#6B4E63]/15 lg:inline-flex"
        title="Cerrar turno de caja"
    >
        <x-heroicon-o-banknotes class="h-4 w-4" />
        Caja 1 · Cerrar
    </a>
@else
    <span class="hidden items-center gap-1.5 rounded-full bg-[#6B4E63]/10 px-3 py-1.5 text-xs font-semibold text-[#6B4E63] lg:inline-flex">
        <x-heroicon-o-banknotes class="h-4 w-4" />
        Caja 1
    </span>
@endif
