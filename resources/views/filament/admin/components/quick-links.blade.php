<nav class="hidden items-center gap-1 lg:flex" aria-label="Accesos rápidos">
    <a
        href="{{ \App\Filament\Pages\Pos\ServiceSelection::getUrl() }}"
        class="rounded-lg px-3 py-1.5 text-sm font-semibold text-gray-500 transition hover:bg-[var(--bw-primary)]/5 hover:text-[var(--bw-primary)]"
    >
        POS
    </a>
    <a
        href="{{ \App\Filament\Pages\Pos\TableSelection::getUrl() }}"
        class="rounded-lg px-3 py-1.5 text-sm font-semibold text-gray-500 transition hover:bg-[var(--bw-primary)]/5 hover:text-[var(--bw-primary)]"
    >
        Tables
    </a>
</nav>
