<div class="flex min-h-[calc(100dvh-6rem)] flex-col gap-8">
    <header class="text-center">
        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-[#6B4E63]">
            Panel de control
        </p>
        <h1 class="mt-3 text-4xl font-bold leading-tight text-[#1D1B1E] md:text-5xl">
            ¿Qué quieres gestionar?
        </h1>
        <p class="mx-auto mt-4 max-w-xl text-base text-gray-500">
            Elige un módulo para comenzar. Todo tu negocio en un solo lugar, rápido y sin fricción.
        </p>
    </header>

    <div class="grid flex-1 grid-cols-1 gap-5 md:grid-cols-3 md:grid-rows-3">
        <a
            href="#"
            class="bw-card-hover bw-btn group relative flex cursor-pointer flex-col justify-between rounded-lg bg-[#6B4E63] p-6 text-white shadow-[0_1px_2px_rgba(29,27,30,0.04),0_2px_6px_rgba(29,27,30,0.04)] md:col-span-2 md:row-span-2 md:p-8"
        >
            <div class="flex items-start justify-between">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-wide">
                    <x-heroicon-o-sparkles class="h-3.5 w-3.5" />
                    Destacado
                </span>
                <span class="inline-flex h-12 w-12 items-center justify-center rounded-lg bg-white/15">
                    <x-heroicon-o-calculator class="h-7 w-7" />
                </span>
            </div>

            <div>
                <h2 class="text-2xl font-bold md:text-3xl">Punto de Venta</h2>
                <p class="mt-2 max-w-md text-sm leading-relaxed text-white/80">
                    Registra ventas, controla el efectivo y emite tickets al instante desde una sola pantalla.
                </p>
            </div>

            <span class="inline-flex w-fit items-center gap-2 rounded-lg bg-white px-5 py-3 text-sm font-bold text-[#6B4E63] transition active:scale-[0.98]">
                Abrir Caja
                <x-heroicon-o-arrow-right class="h-4 w-4 transition group-hover:translate-x-0.5" />
            </span>
        </a>

        <a href="#" class="bw-card bw-card-hover bw-btn group flex cursor-pointer flex-col p-6">
            <div class="flex items-start justify-between">
                <span class="inline-flex h-12 w-12 items-center justify-center rounded-lg bg-[#6B4E63]/10">
                    <x-heroicon-o-clipboard-document-list class="h-6 w-6 text-[#6B4E63]" />
                </span>
                <span class="inline-flex items-center rounded-full bg-[#6B4E63]/5 px-2.5 py-1 text-xs font-semibold text-[#6B4E63]">
                    12 Activos
                </span>
            </div>
            <h2 class="mt-5 text-lg font-bold text-[#1D1B1E]">Pedidos</h2>
            <p class="mt-1 text-sm leading-relaxed text-gray-500">
                Gestiona pedidos de mostrador, delivery y a domicilio.
            </p>
        </a>

        <a href="#" class="bw-card bw-card-hover bw-btn group flex cursor-pointer flex-col p-6">
            <div class="flex items-start justify-between">
                <span class="inline-flex h-12 w-12 items-center justify-center rounded-lg bg-[#6B4E63]/10">
                    <x-heroicon-o-home-modern class="h-6 w-6 text-[#6B4E63]" />
                </span>
                <span class="inline-flex items-center rounded-full bg-[#6B4E63]/5 px-2.5 py-1 text-xs font-semibold text-[#6B4E63]">
                    8
                </span>
            </div>
            <h2 class="mt-5 text-lg font-bold text-[#1D1B1E]">Mesas</h2>
            <p class="mt-1 text-sm leading-relaxed text-gray-500">
                Asigna, abre y cierra mesas en el salón.
            </p>
        </a>

        <a href="#" class="bw-card bw-card-hover bw-btn group flex cursor-pointer flex-col p-6">
            <div class="flex items-start justify-between">
                <span class="inline-flex h-12 w-12 items-center justify-center rounded-lg bg-[#6B4E63]/10">
                    <x-heroicon-o-fire class="h-6 w-6 text-[#6B4E63]" />
                </span>
                <span class="inline-flex items-center rounded-full bg-[#6B4E63]/5 px-2.5 py-1 text-xs font-semibold text-[#6B4E63]">
                    En línea
                </span>
            </div>
            <h2 class="mt-5 text-lg font-bold text-[#1D1B1E]">Cocina</h2>
            <p class="mt-1 text-sm leading-relaxed text-gray-500">
                Visualiza los pedidos en cola y su preparación.
            </p>
        </a>

        <a href="#" class="bw-card bw-card-hover bw-btn group flex cursor-pointer flex-col p-6">
            <div class="flex items-start justify-between">
                <span class="inline-flex h-12 w-12 items-center justify-center rounded-lg bg-[#6B4E63]/10">
                    <x-heroicon-o-users class="h-6 w-6 text-[#6B4E63]" />
                </span>
                <span class="inline-flex items-center rounded-full bg-[#6B4E63]/5 px-2.5 py-1 text-xs font-semibold text-[#6B4E63]">
                    +250
                </span>
            </div>
            <h2 class="mt-5 text-lg font-bold text-[#1D1B1E]">Clientes</h2>
            <p class="mt-1 text-sm leading-relaxed text-gray-500">
                Consulta perfiles, historial y puntos de fidelidad.
            </p>
        </a>

        <a href="#" class="bw-card bw-card-hover bw-btn group flex cursor-pointer items-center gap-4 p-6 md:col-span-3">
            <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-[#6B4E63]/10">
                <x-heroicon-o-chart-bar class="h-6 w-6 text-[#6B4E63]" />
            </span>
            <div class="flex-1">
                <h2 class="text-lg font-bold text-[#1D1B1E]">Informes</h2>
                <p class="mt-0.5 text-sm text-gray-500">
                    Ventas, gastos y resúmenes del día en un vistazo.
                </p>
            </div>
            <x-heroicon-o-arrow-right class="h-5 w-5 text-gray-300 transition group-hover:translate-x-0.5 group-hover:text-[#6B4E63]" />
        </a>
    </div>
</div>
