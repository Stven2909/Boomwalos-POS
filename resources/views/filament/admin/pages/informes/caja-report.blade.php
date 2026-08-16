<x-filament-panels::page>
    <div class="space-y-6">
        <form wire:submit="submitForm" class="bw-informe-filter">
            <div class="bw-informe-filter-field">
                <label>Desde</label>
                <input type="date" wire:model="fechaInicio">
            </div>
            <div class="bw-informe-filter-field">
                <label>Hasta</label>
                <input type="date" wire:model="fechaFin">
            </div>
            @if ($showSucursalFilter)
                <div class="bw-informe-filter-field">
                    <label>Sucursal</label>
                    <select wire:model.live="establecimientoId">
                        <option value="">Todas</option>
                        @foreach (app(\App\Contracts\EstablishmentContextInterface::class)->accessible() as $sucursal)
                            <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <button type="submit" wire:loading.attr="disabled" class="bw-informe-filter-btn">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd"/></svg>
                Consultar
            </button>
        </form>

        @php
            $totalSesiones = count($sesiones);
            $totalVentas = collect($sesiones)->sum('total_ventas');
            $diferenciaPromedio = $totalSesiones > 0 ? collect($sesiones)->avg('diferencia') : 0;
        @endphp

        <div class="bw-informe-kpi-grid">
            <div class="bw-informe-kpi">
                <div class="bw-informe-kpi-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z"/></svg>
                </div>
                <div class="bw-informe-kpi-copy">
                    <span class="bw-informe-kpi-value">{{ $totalSesiones }}</span>
                    <span class="bw-informe-kpi-label">Sesiones cerradas</span>
                </div>
            </div>
            <div class="bw-informe-kpi bw-informe-kpi--green">
                <div class="bw-informe-kpi-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659 1.171-1.671.569.413A7.5 7.5 0 1 0 17.182 8.5H16.5m1.5-3h-1.5m1.5 3h-1.5m-4-3H9m1.5 3H9m1.5 3H9m3-6h.01M12 12h.01M12 15h.01M12 18h.01"/></svg>
                </div>
                <div class="bw-informe-kpi-copy">
                    <span class="bw-informe-kpi-value">${{ number_format($totalVentas, 2) }}</span>
                    <span class="bw-informe-kpi-label">Total ventas</span>
                </div>
            </div>
            <div class="bw-informe-kpi bw-informe-kpi--orange">
                <div class="bw-informe-kpi-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg>
                </div>
                <div class="bw-informe-kpi-copy">
                    <span class="bw-informe-kpi-value">${{ number_format(abs($diferenciaPromedio), 2) }}</span>
                    <span class="bw-informe-kpi-label">Diferencia promedio</span>
                </div>
            </div>
        </div>

        <div class="bw-informe-section">
            <h3 class="bw-informe-section-title">Sesiones cerradas</h3>
            @if (count($sesiones) > 0)
                <table class="bw-informe-table">
                    <thead>
                        <tr>
                            <th>Cierre</th>
                            <th>Abrió</th>
                            <th>Cerró</th>
                            <th>Inicial</th>
                            <th>Ventas</th>
                            <th>Contado</th>
                            <th>Diferencia</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sesiones as $sesion)
                            <tr class="{{ $sesionSeleccionada === $sesion['id'] ? 'bw-informe-table-row--active' : '' }}">
                                <td>{{ $sesion['fecha_cierre'] }}</td>
                                <td>{{ $sesion['usuario_apertura']['nombre'] ?? '-' }}</td>
                                <td>{{ $sesion['usuario_cierre']['nombre'] ?? '-' }}</td>
                                <td>${{ number_format($sesion['monto_inicial'], 2) }}</td>
                                <td class="font-semibold">${{ number_format($sesion['total_ventas'], 2) }}</td>
                                <td>${{ number_format($sesion['efectivo_contado'] ?? 0, 2) }}</td>
                                <td>
                                    @php $diff = $sesion['diferencia'] ?? 0; @endphp
                                    <span class="{{ $diff < 0 ? 'text-red-500' : ($diff > 0 ? 'text-green-600' : '') }}">
                                        ${{ number_format($diff, 2) }}
                                    </span>
                                </td>
                                <td>
                                    <button wire:click="selectSesion({{ $sesion['id'] }})" class="bw-informe-link">Ver pagos</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="bw-informe-empty">No hay sesiones cerradas en el rango seleccionado.</div>
            @endif
        </div>

        @if (count($detallePagos) > 0)
            <div class="bw-informe-section">
                <h3 class="bw-informe-section-title">Pagos de la sesión #{{ $sesionSeleccionada }}</h3>
                <table class="bw-informe-table">
                    <thead>
                        <tr>
                            <th>Pedido</th>
                            <th>Método</th>
                            <th>Recibido</th>
                            <th>Cambio</th>
                            <th>Neto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($detallePagos as $pago)
                            <tr>
                                <td>#{{ $pago['pedido_id'] }}</td>
                                <td>{{ $pago['metodo_pago'] }}</td>
                                <td>${{ number_format($pago['monto_recibido'] ?? 0, 2) }}</td>
                                <td>${{ number_format($pago['cambio_devuelto'] ?? 0, 2) }}</td>
                                <td class="font-semibold">${{ number_format(($pago['monto_recibido'] ?? 0) - ($pago['cambio_devuelto'] ?? 0), 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>
