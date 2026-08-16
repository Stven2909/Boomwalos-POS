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

        <div class="bw-informe-kpi-grid">
            <div class="bw-informe-kpi">
                <div class="bw-informe-kpi-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659 1.171-1.671.569.413A7.5 7.5 0 1 0 17.182 8.5H16.5m1.5-3h-1.5m1.5 3h-1.5m-4-3H9m1.5 3H9m1.5 3H9m3-6h.01M12 12h.01M12 15h.01M12 18h.01"/></svg>
                </div>
                <div class="bw-informe-kpi-copy">
                    <span class="bw-informe-kpi-value">${{ number_format($resumen['total_ventas'], 2) }}</span>
                    <span class="bw-informe-kpi-label">Total ventas</span>
                </div>
            </div>
            <div class="bw-informe-kpi bw-informe-kpi--green">
                <div class="bw-informe-kpi-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15a2.25 2.25 0 0 1 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z"/></svg>
                </div>
                <div class="bw-informe-kpi-copy">
                    <span class="bw-informe-kpi-value">{{ number_format($resumen['cantidad_pedidos']) }}</span>
                    <span class="bw-informe-kpi-label">Pedidos</span>
                </div>
            </div>
            <div class="bw-informe-kpi bw-informe-kpi--orange">
                <div class="bw-informe-kpi-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 15.75V18m-7.5-6.75h.008v.008H8.25v-.008Zm0 2.25h.008v.008H8.25V13.5Zm0 2.25h.008v.008H8.25v-.008Zm0 2.25h.008v.008H8.25V18Zm2.498-6.75h.007v.008h-.007v-.008Zm0 2.25h.007v.008h-.007V13.5Zm0 2.25h.007v.008h-.007v-.008Zm0 2.25h.007v.008h-.007V18Zm2.504-6.75h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V13.5Zm0 2.25h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V18Zm2.498-6.75h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V13.5ZM8.25 6h7.5v2.25h-7.5V6ZM12 2.25c-1.892 0-3.758.11-5.593.322C5.307 2.7 4.5 3.65 4.5 4.757V19.5a2.25 2.25 0 0 0 2.25 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25V4.757c0-1.108-.806-2.057-1.907-2.185A48.507 48.507 0 0 0 12 2.25Z"/></svg>
                </div>
                <div class="bw-informe-kpi-copy">
                    <span class="bw-informe-kpi-value">${{ number_format($resumen['ticket_promedio'], 2) }}</span>
                    <span class="bw-informe-kpi-label">Ticket promedio</span>
                </div>
            </div>
        </div>

        <div class="bw-informe-section">
            <h3 class="bw-informe-section-title">Ventas por método de pago</h3>
            @if (count($metodosPago) > 0)
                <div class="bw-informe-bar-chart">
                    @foreach ($metodosPago as $pago)
                        <div class="bw-informe-bar-row">
                            <div class="bw-informe-bar-name">{{ $pago['metodo_pago'] }} <small>${{ number_format($pago['total'], 2) }}</small></div>
                            <div class="bw-informe-bar-track">
                                <div class="bw-informe-bar-fill" style="width: {{ $pago['porcentaje'] }}%">
                                    <span>{{ $pago['porcentaje'] }}%</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="bw-informe-empty">No hay datos de métodos de pago.</div>
            @endif
        </div>

        <div class="bw-informe-section">
            <h3 class="bw-informe-section-title">Top productos</h3>
            @php
                $maxMonto = count($topProductos) > 0 ? max(array_column($topProductos, 'monto_total')) : 0;
            @endphp
            @if ($maxMonto > 0)
                <table class="bw-informe-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Vendidos</th>
                            <th>Total</th>
                            <th style="width: 200px">Proporción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($topProductos as $prod)
                            <tr>
                                <td>
                                    {{ $prod['nombre'] }}
                                    @if($prod['es_combo'])
                                        <span class="text-xs text-gray-400">(combo)</span>
                                    @endif
                                </td>
                                <td>{{ $prod['cantidad_vendida'] }}</td>
                                <td class="font-semibold">${{ number_format($prod['monto_total'], 2) }}</td>
                                <td>
                                    <div class="bw-informe-progress-track">
                                        <div class="bw-informe-progress-fill" style="width: {{ $maxMonto > 0 ? round(($prod['monto_total'] / $maxMonto) * 100) : 0 }}%"></div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="bw-informe-empty">No hay datos en el rango seleccionado.</div>
            @endif
        </div>

        @if ($showSucursalFilter && count($ventasSucursal) > 1)
            @php
                $maxSucursal = max(array_column($ventasSucursal, 'total'));
            @endphp
            <div class="bw-informe-section">
                <h3 class="bw-informe-section-title">Ventas por sucursal</h3>
                <table class="bw-informe-table">
                    <thead>
                        <tr>
                            <th>Sucursal</th>
                            <th>Total</th>
                            <th style="width: 200px">Proporción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ventasSucursal as $s)
                            <tr>
                                <td>{{ $s['nombre'] }}</td>
                                <td class="font-semibold">${{ number_format($s['total'], 2) }}</td>
                                <td>
                                    <div class="bw-informe-progress-track">
                                        <div class="bw-informe-progress-fill" style="width: {{ $maxSucursal > 0 ? round(($s['total'] / $maxSucursal) * 100) : 0 }}%"></div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>
