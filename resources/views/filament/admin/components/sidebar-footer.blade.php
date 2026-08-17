<div class="space-y-1 px-3 py-3">
    <a
        href="#"
        class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-500 transition hover:bg-[var(--bw-primary)]/5 hover:text-[var(--bw-primary)]"
    >
        <x-heroicon-o-lifebuoy class="h-5 w-5 shrink-0" />
        Soporte
    </a>

    <form method="POST" action="{{ route('filament.admin.auth.logout') }}">
        @csrf
        <button
            type="submit"
            class="flex w-full items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-500 transition hover:bg-red-50 hover:text-red-600"
        >
            <x-heroicon-o-arrow-right-start-on-rectangle class="h-5 w-5 shrink-0" />
            Cerrar Sesión
        </button>
    </form>
</div>
