<x-filament-panels::page>
    @include('filament.admin.components.brand-css')
    <div class="mx-auto w-full max-w-4xl space-y-6">
        <div class="bw-card grid gap-5 p-6 md:grid-cols-2">
            <div class="md:col-span-2">
                <h2 class="text-lg font-bold text-[#1D1B1E]">Identidad y Datos del Negocio</h2>
                <p class="mt-1 text-sm text-gray-500">Estos datos personalizan el nombre comercial, logotipo y la información impresa en los tickets y comandas.</p>
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
                    <span class="text-sm font-semibold text-gray-700">Ruta o URL del logo</span>
                </div>
                <div class="text-sm text-gray-900 flex items-center gap-2">
                    @if ($this->logoPath)
                        <img src="{{ asset($this->logoPath) }}" alt="" class="h-6 w-auto object-contain">
                        <span>{{ $this->logoPath }}</span>
                    @else
                        <span class="text-gray-400">Por defecto (Boomwalos POS)</span>
                    @endif
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

                <div class="md:col-span-2 mt-3 rounded-lg bg-gray-50 p-4 border border-gray-200 text-xs text-gray-600 flex items-center gap-3">
                    <span class="inline-block h-4 w-4 rounded-full" style="background: #6B4E63;"></span>
                    <span><strong>Paleta Visual Institucional:</strong> Fija y optimizada para contraste y rapidez operativa (#6B4E63 / #FF7338).</span>
                </div>
            @else
                <form wire:submit="save" class="contents">
                    <label class="block text-sm font-semibold text-gray-700">Nombre comercial
                        <input wire:model="displayName" type="text" class="mt-2 w-full rounded-lg border-gray-300" required>
                        @error('displayName') <span class="mt-1 block text-xs text-red-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="block text-sm font-semibold text-gray-700">Encabezado del ticket
                        <input wire:model="ticketHeader" type="text" class="mt-2 w-full rounded-lg border-gray-300" placeholder="Ej: Pupusería La Tradición - San Salvador">
                    </label>
                    <label class="block text-sm font-semibold text-gray-700">Ruta o URL del logo
                        <input wire:model="logoPath" type="text" class="mt-2 w-full rounded-lg border-gray-300" placeholder="images/mi-logo.png o https://...">
                    </label>
                    <label class="block text-sm font-semibold text-gray-700">Ruta o URL del favicon
                        <input wire:model="faviconPath" type="text" class="mt-2 w-full rounded-lg border-gray-300" placeholder="images/mi-favicon.png">
                    </label>
                    <label class="block text-sm font-semibold text-gray-700">Teléfono de contacto
                        <input wire:model="contactPhone" type="text" class="mt-2 w-full rounded-lg border-gray-300" placeholder="Ej: 2222-3333">
                    </label>
                    <label class="block text-sm font-semibold text-gray-700">Correo de contacto
                        <input wire:model="contactEmail" type="email" class="mt-2 w-full rounded-lg border-gray-300" placeholder="contacto@empresa.com">
                    </label>
                    <label class="block text-sm font-semibold text-gray-700 md:col-span-2">Pie del ticket
                        <textarea wire:model="ticketFooter" rows="3" class="mt-2 w-full rounded-lg border-gray-300" placeholder="¡Gracias por su compra! Síguenos en Instagram @..."></textarea>
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
