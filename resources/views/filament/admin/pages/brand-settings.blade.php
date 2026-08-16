<x-filament-panels::page>
    <form wire:submit="save" class="mx-auto w-full max-w-4xl space-y-6">
        <div class="bw-card grid gap-5 p-6 md:grid-cols-2">
            <div class="md:col-span-2">
                <h2 class="text-lg font-bold text-[#1D1B1E]">Identidad visible</h2>
                <p class="mt-1 text-sm text-gray-500">Estos datos cambian el nombre, logo y colores que verá tu equipo y tus clientes.</p>
            </div>

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
        </div>

        <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-[#6B4E63] px-5 py-3 text-sm font-bold text-white">
            Guardar cambios
        </button>
    </form>
</x-filament-panels::page>
