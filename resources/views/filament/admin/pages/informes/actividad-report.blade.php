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
            <div class="bw-informe-filter-field">
                <label>Tipo de evento</label>
                <input type="text" wire:model="tipoEvento" placeholder="Ej: pedido_cobrado">
            </div>
            <button type="submit" wire:loading.attr="disabled" class="bw-informe-filter-btn">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd"/></svg>
                Consultar
            </button>
        </form>

        <div class="bw-informe-kpi-grid">
            <div class="bw-informe-kpi">
                <div class="bw-informe-kpi-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 0 1-2.25 2.25M16.5 7.5V18a2.25 2.25 0 0 0 2.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 0 0 2.25 2.25h13.5M6 7.5h3v3H6v-3Z"/></svg>
                </div>
                <div class="bw-informe-kpi-copy">
                    <span class="bw-informe-kpi-value">{{ $totalEventos }}</span>
                    <span class="bw-informe-kpi-label">Total eventos</span>
                </div>
            </div>
            <div class="bw-informe-kpi bw-informe-kpi--green">
                <div class="bw-informe-kpi-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/></svg>
                </div>
                <div class="bw-informe-kpi-copy">
                    <span class="bw-informe-kpi-value">{{ count($eventos) }}</span>
                    <span class="bw-informe-kpi-label">Mostrados en página</span>
                </div>
            </div>
            <div class="bw-informe-kpi bw-informe-kpi--orange">
                <div class="bw-informe-kpi-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z"/></svg>
                </div>
                <div class="bw-informe-kpi-copy">
                    <span class="bw-informe-kpi-value">{{ $totalPaginas }}</span>
                    <span class="bw-informe-kpi-label">Páginas</span>
                </div>
            </div>
        </div>

        <div class="bw-informe-section">
            <h3 class="bw-informe-section-title">Registro de actividad</h3>
            @if (count($eventos) > 0)
                <table class="bw-informe-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Usuario</th>
                            <th>Evento</th>
                            <th>Entidad</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($eventos as $evento)
                            <tr>
                                <td>{{ $evento['created_at'] }}</td>
                                <td>{{ $evento['usuario']['nombre'] ?? '-' }}</td>
                                <td>
                                    <span class="bw-informe-badge">{{ $evento['tipo_evento'] }}</span>
                                </td>
                                <td>{{ class_basename($evento['entidad_tipo']) }} #{{ $evento['entidad_id'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if ($totalPaginas > 1)
                    <div class="bw-informe-pagination">
                        @for ($i = 1; $i <= $totalPaginas; $i++)
                            <button
                                wire:click="goToPage({{ $i }})"
                                class="bw-informe-pagination-btn {{ $paginaActual === $i ? 'bw-informe-pagination-btn--active' : '' }}"
                            >{{ $i }}</button>
                        @endfor
                    </div>
                @endif
            @else
                <div class="bw-informe-empty">No hay eventos en el rango seleccionado.</div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
