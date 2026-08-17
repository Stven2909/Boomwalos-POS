<div class="flex items-center gap-1">
    <a
        href="#"
        aria-label="Notificaciones"
        title="Notificaciones"
        class="relative inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 transition hover:bg-[var(--bw-primary)]/5 hover:text-[var(--bw-primary)]"
    >
        <x-heroicon-o-bell class="h-5 w-5" />
        <span class="absolute end-1.5 top-1.5 h-2 w-2 rounded-full bg-[var(--bw-primary)]"></span>
    </a>

    <a
        href="{{ \App\Filament\Pages\BrandSettings::getUrl() }}"
        aria-label="Ajustes"
        title="Ajustes"
        class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 transition hover:bg-[var(--bw-primary)]/5 hover:text-[var(--bw-primary)]"
    >
        <x-heroicon-o-cog-6-tooth class="h-5 w-5" />
    </a>
</div>
