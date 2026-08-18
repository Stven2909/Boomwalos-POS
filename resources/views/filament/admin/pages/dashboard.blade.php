@include('filament.admin.components.brand-css')
@if (auth()->user()?->hasRole('cajero'))
    <div class="bw-cashier-dashboard" data-testid="cashier-dashboard">
        <header class="bw-cashier-header">
            <div class="bw-cashier-brand" aria-label="{{ $posBranding->displayName() }}">
                <img
                    src="{{ $posBranding->logoUrl() }}"
                    alt=""
                    class="bw-cashier-logo"
                >
                <span class="bw-cashier-brand-name">{{ mb_strtoupper($posBranding->displayName()) }}</span>
            </div>

            <div class="bw-cashier-session-group">
                <p class="bw-cashier-session">
                    {{ mb_strtoupper(auth()->user()?->getFilamentName() ?? 'CAJERO') }} <span aria-hidden="true">·</span> CAJA 1
                </p>

                <form action="{{ filament()->getLogoutUrl() }}" method="post" class="bw-cashier-logout-form">
                    @csrf
                    <button type="submit" class="bw-cashier-logout" aria-label="Cerrar sesión">
                        <x-heroicon-o-arrow-left-end-on-rectangle class="h-4 w-4" />
                        <span>Salir</span>
                    </button>
                </form>
            </div>
        </header>

        <main class="bw-cashier-main">
            <section aria-labelledby="cashier-dashboard-title">
                <h1 id="cashier-dashboard-title" class="bw-cashier-title">¿Qué quieres gestionar?</h1>
                <p class="bw-cashier-subtitle">Selecciona un módulo para iniciar una tarea</p>
            </section>

            <nav
                class="bw-cashier-modules"
                aria-label="Módulos disponibles"
                x-data="{
                    active: ['punto-de-venta', 'pedidos', 'mesas', 'clientes', 'informes'].includes(window.location.hash.slice(1))
                        ? window.location.hash.slice(1)
                        : 'punto-de-venta'
                }"
                @hashchange.window="active = ['punto-de-venta', 'pedidos', 'mesas', 'clientes', 'informes'].includes(window.location.hash.slice(1)) ? window.location.hash.slice(1) : 'punto-de-venta'"
            >
                <a href="{{ \App\Filament\Pages\Pos\ServiceSelection::getUrl() }}" @click="active = 'punto-de-venta'" :class="{ 'bw-cashier-module-primary': active === 'punto-de-venta' }" :aria-current="active === 'punto-de-venta' ? 'page' : null" class="bw-cashier-module" data-module="punto-de-venta">
                    <span class="bw-cashier-module-icon" aria-hidden="true">
                        <x-heroicon-o-squares-2x2 class="h-7 w-7" />
                    </span>
                    <span class="bw-cashier-module-copy">
                        <span class="bw-cashier-module-title">Punto de venta</span>
                        <span class="bw-cashier-module-description">Tomar pedidos y cobrar</span>
                    </span>
                </a>

                <a href="#pedidos" @click="active = 'pedidos'" :class="{ 'bw-cashier-module-primary': active === 'pedidos' }" :aria-current="active === 'pedidos' ? 'page' : null" class="bw-cashier-module" data-module="pedidos">
                    <span class="bw-cashier-module-icon" aria-hidden="true">
                        <x-heroicon-o-clipboard-document-list class="h-7 w-7" />
                    </span>
                    <span class="bw-cashier-module-copy">
                        <span class="bw-cashier-module-title">Pedidos</span>
                        <span class="bw-cashier-module-description">Ver órdenes activas</span>
                    </span>
                </a>

                <a href="{{ \App\Filament\Pages\Pos\TableSelection::getUrl(['tipo' => \App\Enums\TipoPedido::MESA->value, 'entrada' => 'mesas']) }}" @click="active = 'mesas'" :class="{ 'bw-cashier-module-primary': active === 'mesas' }" :aria-current="active === 'mesas' ? 'page' : null" class="bw-cashier-module" data-module="mesas">
                    <span class="bw-cashier-module-icon" aria-hidden="true">
                        <x-heroicon-o-home-modern class="h-7 w-7" />
                    </span>
                    <span class="bw-cashier-module-copy">
                        <span class="bw-cashier-module-title">Mesas</span>
                        <span class="bw-cashier-module-description">Sala y terraza</span>
                    </span>
                </a>

                <a href="#clientes" @click="active = 'clientes'" :class="{ 'bw-cashier-module-primary': active === 'clientes' }" :aria-current="active === 'clientes' ? 'page' : null" class="bw-cashier-module" data-module="clientes">
                    <span class="bw-cashier-module-icon" aria-hidden="true">
                        <x-heroicon-o-users class="h-7 w-7" />
                    </span>
                    <span class="bw-cashier-module-copy">
                        <span class="bw-cashier-module-title">Clientes</span>
                        <span class="bw-cashier-module-description">Historial y fidelidad</span>
                    </span>
                </a>

                <a href="#informes" @click="active = 'informes'" :class="{ 'bw-cashier-module-primary': active === 'informes' }" :aria-current="active === 'informes' ? 'page' : null" class="bw-cashier-module" data-module="informes">
                    <span class="bw-cashier-module-icon" aria-hidden="true">
                        <x-heroicon-o-chart-bar class="h-7 w-7" />
                    </span>
                    <span class="bw-cashier-module-copy">
                        <span class="bw-cashier-module-title">Informes</span>
                        <span class="bw-cashier-module-description">Ventas y caja</span>
                    </span>
                </a>
            </nav>
        </main>
    </div>
@elseif (auth()->user()?->hasRole('administrador'))
    <div class="flex min-h-[calc(100dvh-6rem)] flex-col gap-8">
        <header class="text-center">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-[var(--bw-primary)]">
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
                href="{{ \App\Filament\Pages\Pos\ServiceSelection::getUrl() }}"
                class="bw-card-hover bw-btn group relative flex cursor-pointer flex-col justify-between rounded-lg bg-[var(--bw-primary)] p-6 text-white shadow-[0_1px_2px_rgba(29,27,30,0.04),0_2px_6px_rgba(29,27,30,0.04)] md:col-span-2 md:row-span-2 md:p-8"
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

                <span class="inline-flex w-fit items-center gap-2 rounded-lg bg-white px-5 py-3 text-sm font-bold text-[var(--bw-primary)] transition active:scale-[0.98]">
                    Abrir Caja
                    <x-heroicon-o-arrow-right class="h-4 w-4 transition group-hover:translate-x-0.5" />
                </span>
            </a>

            <a href="{{ \App\Filament\Pages\Pos\ListaPedidos::getUrl() }}" class="bw-card bw-card-hover bw-btn group flex cursor-pointer flex-col p-6">
                <div class="flex items-start justify-between">
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-lg bg-[var(--bw-primary)]/10">
                        <x-heroicon-o-clipboard-document-list class="h-6 w-6 text-[var(--bw-primary)]" />
                    </span>
                    <span class="inline-flex items-center rounded-full bg-[var(--bw-primary)]/5 px-2.5 py-1 text-xs font-semibold text-[var(--bw-primary)]">
                        12 Activos
                    </span>
                </div>
                <h2 class="mt-5 text-lg font-bold text-[#1D1B1E]">Pedidos</h2>
                <p class="mt-1 text-sm leading-relaxed text-gray-500">
                    Gestiona pedidos de mostrador, delivery y a domicilio.
                </p>
            </a>

            <a href="{{ \App\Filament\Resources\Mesas\MesaResource::getUrl() }}" class="bw-card bw-card-hover bw-btn group flex cursor-pointer flex-col p-6">
                <div class="flex items-start justify-between">
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-lg bg-[var(--bw-primary)]/10">
                        <x-heroicon-o-home-modern class="h-6 w-6 text-[var(--bw-primary)]" />
                    </span>
                    <span class="inline-flex items-center rounded-full bg-[var(--bw-primary)]/5 px-2.5 py-1 text-xs font-semibold text-[var(--bw-primary)]">
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
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-lg bg-[var(--bw-primary)]/10">
                        <x-heroicon-o-users class="h-6 w-6 text-[var(--bw-primary)]" />
                    </span>
                    <span class="inline-flex items-center rounded-full bg-[var(--bw-primary)]/5 px-2.5 py-1 text-xs font-semibold text-[var(--bw-primary)]">
                        +250
                    </span>
                </div>
                <h2 class="mt-5 text-lg font-bold text-[#1D1B1E]">Clientes</h2>
                <p class="mt-1 text-sm leading-relaxed text-gray-500">
                    Consulta perfiles, historial y puntos de fidelidad.
                </p>
            </a>

            <a href="#" class="bw-card bw-card-hover bw-btn group flex cursor-pointer items-center gap-4 p-6 md:col-span-3">
                <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-[var(--bw-primary)]/10">
                    <x-heroicon-o-chart-bar class="h-6 w-6 text-[var(--bw-primary)]" />
                </span>
                <div class="flex-1">
                    <h2 class="text-lg font-bold text-[#1D1B1E]">Informes</h2>
                    <p class="mt-0.5 text-sm text-gray-500">
                        Ventas, gastos y resúmenes del día en un vistazo.
                    </p>
                </div>
                <x-heroicon-o-arrow-right class="h-5 w-5 text-gray-300 transition group-hover:translate-x-0.5 group-hover:text-[var(--bw-primary)]" />
            </a>
        </div>
    </div>
@else
    <div class="flex min-h-[calc(100dvh-6rem)] items-center justify-center p-6">
        <section class="bw-card max-w-lg p-8 text-center" aria-labelledby="access-denied-title">
            <h1 id="access-denied-title" class="text-2xl font-bold text-[#1D1B1E]">Acceso no configurado</h1>
            <p class="mt-3 text-sm leading-relaxed text-gray-500">
                Tu usuario todavía no tiene un rol operativo asignado. Solicita al administrador que configure tus permisos.
            </p>
        </section>
    </div>
@endif
