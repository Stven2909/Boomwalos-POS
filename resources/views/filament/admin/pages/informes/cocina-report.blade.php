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
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                </div>
                <div class="bw-informe-kpi-copy">
                    <span class="bw-informe-kpi-value">{{ $tiempos['pendiente_preparacion'] }} min</span>
                    <span class="bw-informe-kpi-label">Pendiente a preparación</span>
                </div>
            </div>
            <div class="bw-informe-kpi bw-informe-kpi--orange">
                <div class="bw-informe-kpi-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0 1 12 21 8.25 8.25 0 0 1 6.038 7.047 8.287 8.287 0 0 0 9 9.601a8.983 8.983 0 0 1 3.361-6.867 8.21 8.21 0 0 0 3 2.48Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 18a3.75 3.75 0 0 0 .495-7.468 5.99 5.99 0 0 0-1.925 3.547 5.975 5.975 0 0 1-2.133-1.001A3.75 3.75 0 0 0 12 18Z"/></svg>
                </div>
                <div class="bw-informe-kpi-copy">
                    <span class="bw-informe-kpi-value">{{ $tiempos['preparacion_lista'] }} min</span>
                    <span class="bw-informe-kpi-label">Preparación a lista</span>
                </div>
            </div>
            <div class="bw-informe-kpi bw-informe-kpi--green">
                <div class="bw-informe-kpi-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H18.75m-7.5-3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                </div>
                <div class="bw-informe-kpi-copy">
                    <span class="bw-informe-kpi-value">{{ $tiempos['lista_entregada'] }} min</span>
                    <span class="bw-informe-kpi-label">Lista a entregada</span>
                </div>
            </div>
            <div class="bw-informe-kpi">
                <div class="bw-informe-kpi-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                </div>
                <div class="bw-informe-kpi-copy">
                    <span class="bw-informe-kpi-value">{{ $tiempos['total_completadas'] }}</span>
                    <span class="bw-informe-kpi-label">Tandas completadas</span>
                </div>
            </div>
        </div>

        <div class="bw-informe-section">
            <h3 class="bw-informe-section-title">Tandas por estado actual</h3>
            @php
                $maxEstado = count($volumen['por_estado']) > 0 ? $volumen['por_estado']->max() : 0;
            @endphp
            @if ($maxEstado > 0)
                <div class="bw-informe-bar-chart">
                    @foreach ($volumen['por_estado'] as $estado => $total)
                        <div class="bw-informe-bar-row">
                            <div class="bw-informe-bar-name">{{ $estado }}</div>
                            <div class="bw-informe-bar-track">
                                <div class="bw-informe-bar-fill" style="width: {{ $maxEstado > 0 ? round(($total / $maxEstado) * 100) : 0 }}%">
                                    <span>{{ $total }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="bw-informe-table-footer-row mt-3 pt-3 border-t border-gray-100 flex justify-between">
                    <span class="text-sm font-semibold text-gray-600">Total tandas</span>
                    <span class="text-sm font-bold text-gray-900">{{ $volumen['total_tandas'] }}</span>
                </div>
            @else
                <div class="bw-informe-empty">No hay tandas registradas.</div>
            @endif
        </div>

        @if (count($volumen['por_sucursal']) > 0)
            @php
                $maxTandasSuc = $volumen['por_sucursal']->max('total');
            @endphp
            <div class="bw-informe-section">
                <h3 class="bw-informe-section-title">Tandas por sucursal en el período</h3>
                <table class="bw-informe-table">
                    <thead>
                        <tr>
                            <th>Sucursal</th>
                            <th>Tandas</th>
                            <th style="width: 200px">Proporción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($volumen['por_sucursal'] as $s)
                            <tr>
                                <td>{{ $s['sucursal'] }}</td>
                                <td class="font-semibold">{{ $s['total'] }}</td>
                                <td>
                                    <div class="bw-informe-progress-track">
                                        <div class="bw-informe-progress-fill" style="width: {{ $maxTandasSuc > 0 ? round(($s['total'] / $maxTandasSuc) * 100) : 0 }}%"></div>
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
