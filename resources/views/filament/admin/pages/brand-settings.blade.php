<x-filament-panels::page>
    @include('filament.admin.components.brand-css')
    <div class="mx-auto w-full max-w-4xl space-y-6">
        <div class="bw-card grid gap-5 p-6 md:grid-cols-2">
            <div class="md:col-span-2">
                <h2 class="text-lg font-bold text-[#1D1B1E]">Identidad visible</h2>
                <p class="mt-1 text-sm text-gray-500">Estos datos cambian el nombre, logo y colores que verá tu equipo y tus clientes.</p>
            </div>

            @if (! $this->editing)
                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-gray-700">Nombre comercial</span>
                </div>
                <div class="text-sm text-gray-900">{{ $this->displayName ?: '—' }}</div>

                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-gray-700">Encabezado del ticket</span>
                </div>
                <div class="text-sm text-gray-900">{{ $this->ticketHeader ?: '—' }}</div>

                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-gray-700">Ruta del logo</span>
                </div>
                <div class="text-sm text-gray-900">{{ $this->logoPath ?: '—' }}</div>

                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-gray-700">Ruta del favicon</span>
                </div>
                <div class="text-sm text-gray-900">{{ $this->faviconPath ?: '—' }}</div>

                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-gray-700">Color principal</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-block h-5 w-5 rounded-full border border-gray-200" style="background: {{ $this->primaryColor }}"></span>
                    <span class="text-sm text-gray-900">{{ $this->primaryColor }}</span>
                </div>

                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-gray-700">Color secundario</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-block h-5 w-5 rounded-full border border-gray-200" style="background: {{ $this->secondaryColor }}"></span>
                    <span class="text-sm text-gray-900">{{ $this->secondaryColor }}</span>
                </div>

                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-gray-700">Teléfono de contacto</span>
                </div>
                <div class="text-sm text-gray-900">{{ $this->contactPhone ?: '—' }}</div>

                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-gray-700">Correo de contacto</span>
                </div>
                <div class="text-sm text-gray-900">{{ $this->contactEmail ?: '—' }}</div>

                <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-gray-700">Pie del ticket</span>
                </div>
                <div class="text-sm text-gray-900 md:col-span-2 whitespace-pre-line">{{ $this->ticketFooter ?: '—' }}</div>
            @else
                <form wire:submit="save" class="contents">
                    <label class="block text-sm font-semibold text-gray-700">Nombre comercial
                        <input wire:model="displayName" type="text" class="mt-2 w-full rounded-lg border-gray-300" required>
                        @error('displayName') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="block text-sm font-semibold text-gray-700">Encabezado del ticket
                        <input wire:model="ticketHeader" type="text" class="mt-2 w-full rounded-lg border-gray-300">
                    </label>
                    <label class="block text-sm font-semibold text-gray-700">Ruta del logo
                        <input wire:model="logoPath" type="text" class="mt-2 w-full rounded-lg border-gray-300" placeholder="images/mi-logo.png">
                    </label>
                    <label class="block text-sm font-semibold text-gray-700">Ruta del favicon
                        <input wire:model="faviconPath" type="text" class="mt-2 w-full rounded-lg border-gray-300" placeholder="images/mi-favicon.png">
                    </label>
                    <label class="block text-sm font-semibold text-gray-700">Color principal
                        <input wire:model="primaryColor" type="text" class="mt-2 w-full rounded-lg border-gray-300" placeholder="#6B4E63">
                        @error('primaryColor') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="block text-sm font-semibold text-gray-700">Color secundario
                        <input wire:model="secondaryColor" type="text" class="mt-2 w-full rounded-lg border-gray-300" placeholder="#F6F1EE">
                        @error('secondaryColor') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="block text-sm font-semibold text-gray-700">Teléfono de contacto
                        <input wire:model="contactPhone" type="text" class="mt-2 w-full rounded-lg border-gray-300">
                    </label>
                    <label class="block text-sm font-semibold text-gray-700">Correo de contacto
                        <input wire:model="contactEmail" type="email" class="mt-2 w-full rounded-lg border-gray-300">
                    </label>
                    <label class="block text-sm font-semibold text-gray-700 md:col-span-2">Pie del ticket
                        <textarea wire:model="ticketFooter" rows="3" class="mt-2 w-full rounded-lg border-gray-300"></textarea>
                    </label>
                </form>
            @endif
        </div>

        <div class="flex gap-3">
            @if (! $this->editing)
                <button wire:click="startEditing" class="rounded-lg bg-[var(--bw-primary)] px-5 py-3 text-sm font-bold text-white">
                    Editar
                </button>
            @else
                <button wire:click="save" wire:loading.attr="disabled" class="rounded-lg bg-[var(--bw-primary)] px-5 py-3 text-sm font-bold text-white">
                    Guardar cambios
                </button>
                <button wire:click="cancelEditing" type="button" class="rounded-lg border border-gray-300 px-5 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    Cancelar
                </button>
            @endif
        </div>
    </div>
</x-filament-panels::page>
