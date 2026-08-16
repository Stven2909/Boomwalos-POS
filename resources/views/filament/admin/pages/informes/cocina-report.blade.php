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
            @if ($showSucursalFilter)
                <div>
                    <label class="block text-sm font-semibold text-gray-700">Sucursal</label>
                    <select wire:model.live="establecimientoId" class="mt-1 rounded-lg border-gray-300">
                        <option value="">Todas</option>
                        @foreach (app(\App\Contracts\EstablishmentContextInterface::class)->accessible() as $sucursal)
                            <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-[#6B4E63] px-4 py-2 text-sm font-bold text-white">Consultar</button>
        </form>

        <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-lg border bg-white p-4 shadow-sm">
                <span class="text-sm text-gray-500">Pendiente → Preparación</span>
                <p class="text-2xl font-bold">{{ $tiempos['pendiente_preparacion'] }} min</p>
            </div>
            <div class="rounded-lg border bg-white p-4 shadow-sm">
                <span class="text-sm text-gray-500">Preparación → Lista</span>
                <p class="text-2xl font-bold">{{ $tiempos['preparacion_lista'] }} min</p>
            </div>
            <div class="rounded-lg border bg-white p-4 shadow-sm">
                <span class="text-sm text-gray-500">Lista → Entregada</span>
                <p class="text-2xl font-bold">{{ $tiempos['lista_entregada'] }} min</p>
            </div>
            <div class="rounded-lg border bg-white p-4 shadow-sm">
                <span class="text-sm text-gray-500">Tandas completadas</span>
                <p class="text-2xl font-bold">{{ $tiempos['total_completadas'] }}</p>
            </div>
        </div>

        <div class="rounded-lg border bg-white p-4 shadow-sm">
            <h3 class="mb-3 font-bold">Tandas por estado actual</h3>
            <table class="w-full text-sm">
                <thead><tr class="border-b text-left text-gray-500"><th>Estado</th><th class="text-right">Total</th></tr></thead>
                <tbody>
                @forelse ($volumen['por_estado'] as $estado => $total)
                    <tr class="border-b">
                        <td>{{ $estado }}</td>
                        <td class="text-right">{{ $total }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="py-4 text-center text-gray-400">No hay tandas registradas.</td></tr>
                @endforelse
                <tr class="font-bold"><td>Total</td><td class="text-right">{{ $volumen['total_tandas'] }}</td></tr>
                </tbody>
            </table>
        </div>

        @if (count($volumen['por_sucursal']) > 0)
            <div class="rounded-lg border bg-white p-4 shadow-sm">
                <h3 class="mb-3 font-bold">Tandas por sucursal en el período</h3>
                <table class="w-full text-sm">
                    <thead><tr class="border-b text-left text-gray-500"><th>Sucursal</th><th class="text-right">Tandas</th></tr></thead>
                    <tbody>
                    @foreach ($volumen['por_sucursal'] as $s)
                        <tr class="border-b">
                            <td>{{ $s['establecimiento_id'] }}</td>
                            <td class="text-right">{{ $s['total'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>
