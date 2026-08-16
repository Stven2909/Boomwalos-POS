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

        <div class="rounded-lg border bg-white p-4 shadow-sm">
            <h3 class="mb-3 font-bold">Sesiones cerradas</h3>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left text-gray-500">
                        <th>Cierre</th>
                        <th>Abrió</th>
                        <th>Cerró</th>
                        <th class="text-right">Inicial</th>
                        <th class="text-right">Ventas</th>
                        <th class="text-right">Contado</th>
                        <th class="text-right">Diferencia</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($sesiones as $sesion)
                    <tr class="border-b {{ $sesionSeleccionada === $sesion['id'] ? 'bg-gray-50' : '' }}">
                        <td>{{ $sesion['fecha_cierre'] }}</td>
                        <td>{{ $sesion['usuario_apertura']['name'] ?? '-' }}</td>
                        <td>{{ $sesion['usuario_cierre']['name'] ?? '-' }}</td>
                        <td class="text-right">${{ number_format($sesion['monto_inicial'], 2) }}</td>
                        <td class="text-right">${{ number_format($sesion['total_ventas'], 2) }}</td>
                        <td class="text-right">${{ number_format($sesion['efectivo_contado'] ?? 0, 2) }}</td>
                        <td class="text-right">${{ number_format($sesion['diferencia'] ?? 0, 2) }}</td>
                        <td><button wire:click="selectSesion({{ $sesion['id'] }})" class="text-sm text-[#6B4E63] underline">Ver pagos</button></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-4 text-center text-gray-400">No hay sesiones cerradas en el rango seleccionado.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if (count($detallePagos) > 0)
            <div class="rounded-lg border bg-white p-4 shadow-sm">
                <h3 class="mb-3 font-bold">Pagos de la sesión #{{ $sesionSeleccionada }}</h3>
                <table class="w-full text-sm">
                    <thead><tr class="border-b text-left text-gray-500"><th>Pedido</th><th>Método</th><th class="text-right">Recibido</th><th class="text-right">Cambio</th><th class="text-right">Neto</th></tr></thead>
                    <tbody>
                    @foreach ($detallePagos as $pago)
                        <tr class="border-b">
                            <td>#{{ $pago['pedido_id'] }}</td>
                            <td>{{ $pago['metodo_pago'] }}</td>
                            <td class="text-right">${{ number_format($pago['monto_recibido'] ?? 0, 2) }}</td>
                            <td class="text-right">${{ number_format($pago['cambio_devuelto'] ?? 0, 2) }}</td>
                            <td class="text-right">${{ number_format(($pago['monto_recibido'] ?? 0) - ($pago['cambio_devuelto'] ?? 0), 2) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>
