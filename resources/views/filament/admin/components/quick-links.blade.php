<nav class="hidden items-center gap-1 lg:flex" aria-label="Accesos rápidos">
    <a
        href="{{ \App\Filament\Pages\Pos\ServiceSelection::getUrl() }}"
        class="rounded-lg px-3 py-1.5 text-sm font-semibold text-gray-500 transition hover:bg-[#6B4E63]/5 hover:text-[#6B4E63]"
    >
        POS
    </a>
    <a
        href="{{ \App\Filament\Pages\Kitchen\KitchenDisplay::getUrl() }}"
        class="rounded-lg px-3 py-1.5 text-sm font-semibold text-gray-500 transition hover:bg-[#6B4E63]/5 hover:text-[#6B4E63]"
    >
        Kitchen
    </a>
    <a
        href="{{ \App\Filament\Pages\Pos\TableSelection::getUrl() }}"
        class="rounded-lg px-3 py-1.5 text-sm font-semibold text-gray-500 transition hover:bg-[#6B4E63]/5 hover:text-[#6B4E63]"
    >
        Tables
    </a>
</nav>
