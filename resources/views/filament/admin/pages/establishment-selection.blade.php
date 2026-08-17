<x-filament-panels::page>
    @include('filament.admin.components.brand-css')
    <div class="mx-auto w-full max-w-3xl space-y-6">
        <header>
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-[var(--bw-primary)]">{{ $posBranding->displayName() }}</p>
            <h1 class="mt-2 text-3xl font-bold text-[#1D1B1E]">Selecciona una sucursal</h1>
            <p class="mt-2 text-sm text-gray-500">Trabajarás únicamente con los pedidos y la caja de la sucursal elegida.</p>
        </header>

        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($this->establishments() as $establishment)
                <button
                    type="button"
                    wire:click="select({{ $establishment->getKey() }})"
                    wire:loading.attr="disabled"
                    class="bw-card bw-card-hover flex items-start gap-4 p-6 text-left"
                >
                    <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-[var(--bw-primary)]/10 text-[var(--bw-primary)]">
                        <x-heroicon-o-building-storefront class="h-6 w-6" />
                    </span>
                    <span>
                        <strong class="block text-lg text-[#1D1B1E]">{{ $establishment->nombre }}</strong>
                        <span class="mt-1 block text-sm text-gray-500">{{ $establishment->direccion }}</span>
                    </span>
                </button>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
