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

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-lg border bg-white p-4 shadow-sm">
                <span class="text-sm text-gray-500">Total ventas</span>
                <p class="text-2xl font-bold">${{ number_format($resumen['total_ventas'], 2) }}</p>
            </div>
            <div class="rounded-lg border bg-white p-4 shadow-sm">
                <span class="text-sm text-gray-500">Pedidos</span>
                <p class="text-2xl font-bold">{{ number_format($resumen['cantidad_pedidos']) }}</p>
            </div>
            <div class="rounded-lg border bg-white p-4 shadow-sm">
                <span class="text-sm text-gray-500">Ticket promedio</span>
                <p class="text-2xl font-bold">${{ number_format($resumen['ticket_promedio'], 2) }}</p>
            </div>
        </div>

        <div class="rounded-lg border bg-white p-4 shadow-sm">
            <h3 class="mb-3 font-bold">Ventas por método de pago</h3>
            <table class="w-full text-sm">
                <thead><tr class="border-b text-left text-gray-500"><th>Método</th><th class="text-right">Total</th><th class="text-right">%</th></tr></thead>
                <tbody>
                @foreach ($metodosPago as $pago)
                    <tr class="border-b">
                        <td>{{ $pago['metodo_pago'] }}</td>
                        <td class="text-right">${{ number_format($pago['total'], 2) }}</td>
                        <td class="text-right">{{ $pago['porcentaje'] }}%</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="rounded-lg border bg-white p-4 shadow-sm">
            <h3 class="mb-3 font-bold">Top productos</h3>
            <table class="w-full text-sm">
                <thead><tr class="border-b text-left text-gray-500"><th>Producto</th><th class="text-right">Cantidad</th><th class="text-right">Total</th></tr></thead>
                <tbody>
                @forelse ($topProductos as $prod)
                    <tr class="border-b">
                        <td>{{ $prod['nombre'] }} @if($prod['es_combo'])<span class="text-xs text-gray-400">(combo)</span>@endif</td>
                        <td class="text-right">{{ $prod['cantidad_vendida'] }}</td>
                        <td class="text-right">${{ number_format($prod['monto_total'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-4 text-center text-gray-400">No hay datos en el rango seleccionado.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($showSucursalFilter && count($ventasSucursal) > 1)
            <div class="rounded-lg border bg-white p-4 shadow-sm">
                <h3 class="mb-3 font-bold">Ventas por sucursal</h3>
                <table class="w-full text-sm">
                    <thead><tr class="border-b text-left text-gray-500"><th>Sucursal</th><th class="text-right">Total</th></tr></thead>
                    <tbody>
                    @foreach ($ventasSucursal as $s)
                        <tr class="border-b">
                            <td>{{ $s['nombre'] }}</td>
                            <td class="text-right">${{ number_format($s['total'], 2) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>
