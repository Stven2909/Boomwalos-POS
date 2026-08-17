<div class="space-y-4">
        @forelse ($this->groups as $group)
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
                {{-- Group header --}}
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <div class="flex items-center gap-3">
                        @if ($group->iconoType() === 'image')
                            <img src="{{ $group->iconoUrl() }}" class="h-8 w-8" alt="">
                        @elseif ($group->icono)
                            <span class="text-2xl">{{ $group->icono }}</span>
                        @else
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gray-100 text-sm">📁</span>
                        @endif
                        <div>
                            <h3 class="text-base font-bold text-gray-900">{{ $group->nombre }}</h3>
                            @if ($group->descripcion)
                                <p class="text-xs text-gray-500">{{ $group->descripcion }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-700">
                            {{ $group->productos_count }} {{ Str::plural('producto', $group->productos_count) }}
                        </span>
                        @unless ($group->activa)
                            <span class="inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-semibold text-yellow-800">Inactiva</span>
                        @endunless
                        <x-filament::icon-button
                            wire:click="editCategoria({{ $group->id }})"
                            icon="heroicon-m-pencil"
                            label="Editar"
                            size="sm"
                        />
                    </div>
                </div>

                {{-- Categories grid --}}
                @if ($group->children->count())
                    <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($group->children as $cat)
                            <div class="relative rounded-lg border border-gray-100 bg-gray-50 p-4 transition hover:border-gray-200 hover:bg-white">
                                <div class="mb-2 flex items-center gap-2">
                                    @if ($cat->iconoType() === 'image')
                                        <img src="{{ $cat->iconoUrl() }}" class="h-5 w-5" alt="">
                                    @elseif ($cat->icono)
                                        <span class="text-lg">{{ $cat->icono }}</span>
                                    @endif
                                    <span class="text-sm font-semibold text-gray-900">{{ $cat->nombre }}</span>
                                </div>

                                <div class="mb-3 flex items-center gap-2 text-xs text-gray-500">
                                    <span>{{ $cat->productos_count }} {{ Str::plural('producto', $cat->productos_count) }}</span>
                                    @unless ($cat->activa)
                                        <span class="inline-flex items-center rounded-full bg-yellow-100 px-1.5 py-0.5 text-[10px] font-semibold text-yellow-800">Inactiva</span>
                                    @endunless
                                </div>

                                <div class="absolute right-2 top-2 flex items-center gap-1">
                                    <x-filament::icon-button
                                        wire:click="editCategoria({{ $cat->id }})"
                                        icon="heroicon-m-pencil"
                                        label="Editar"
                                        size="xs"
                                    />
                                    <x-filament::icon-button
                                        wire:click="deleteCategoria({{ $cat->id }})"
                                        icon="heroicon-m-trash"
                                        label="Eliminar"
                                        size="xs"
                                        color="danger"
                                    />
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="px-5 py-4 text-sm text-gray-400">
                        Sin subcategorías. Crea una con el botón "Nueva categoría".
                    </div>
                @endif
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-12 text-center">
                <span class="text-4xl">📂</span>
                <h3 class="mt-3 text-sm font-semibold text-gray-900">No hay grupos</h3>
                <p class="mt-1 text-sm text-gray-500">Crea el primer grupo con el botón "Nueva categoría".</p>
            </div>
        @endforelse
</div>
