<x-filament-panels::page>
    <div class="space-y-6">
        <form wire:submit="submitForm" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700">Desde</label>
                <input type="date" wire:model="fechaInicio" class="mt-1 rounded-lg border-gray-300">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700">Hasta</label>
                <input type="date" wire:model="fechaFin" class="mt-1 rounded-lg border-gray-300">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700">Tipo de evento</label>
                <input type="text" wire:model="tipoEvento" class="mt-1 rounded-lg border-gray-300" placeholder="Ej: pedido_cobrado">
            </div>
            <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-[#6B4E63] px-4 py-2 text-sm font-bold text-white">Consultar</button>
        </form>

        <div class="rounded-lg border bg-white p-4 shadow-sm">
            <h3 class="mb-3 font-bold">Registro de actividad</h3>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left text-gray-500">
                        <th>Fecha</th>
                        <th>Usuario</th>
                        <th>Evento</th>
                        <th>Entidad</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($eventos as $evento)
                    <tr class="border-b">
                        <td>{{ $evento['created_at'] }}</td>
                        <td>{{ $evento['usuario']['name'] ?? '-' }}</td>
                        <td>{{ $evento['tipo_evento'] }}</td>
                        <td>{{ class_basename($evento['entidad_tipo']) }} #{{ $evento['entidad_id'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-center text-gray-400">No hay eventos en el rango seleccionado.</td></tr>
                @endforelse
                </tbody>
            </table>

            @if ($totalPaginas > 1)
                <div class="mt-4 flex justify-center gap-2">
                    @for ($i = 1; $i <= $totalPaginas; $i++)
                        <button
                            wire:click="goToPage({{ $i }})"
                            class="rounded px-3 py-1 text-sm {{ $paginaActual === $i ? 'bg-[#6B4E63] text-white' : 'bg-gray-100 hover:bg-gray-200' }}"
                        >{{ $i }}</button>
                    @endfor
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
