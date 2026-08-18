<x-filament-panels::page>
    @include('filament.admin.components.brand-css')

    @if ($feedback)
        <div class="mb-4 rounded-lg bg-primary-50 p-4 text-sm text-primary-700 dark:bg-primary-950 dark:text-primary-300" role="status">
            {{ $feedback }}
        </div>
    @endif

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <div class="flex items-center gap-2">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Estado:</label>
            <select wire:change="setFilterEstado($event.target.value)" class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                @foreach ($this->estadoOptions() as $value => $label)
                    <option value="{{ $value }}" {{ $filterEstado === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex items-center gap-2">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Tipo:</label>
            <select wire:change="setFilterTipo($event.target.value)" class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                @foreach ($this->tipoOptions() as $value => $label)
                    <option value="{{ $value }}" {{ $filterTipo === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        @if ($this->failedCount() > 0)
            <button
                wire:click="retryAllFailed"
                wire:confirm="¿Reenviar todos los trabajos fallidos a la cola?"
                class="rounded-lg bg-danger-600 px-3 py-1.5 text-sm font-semibold text-white transition hover:bg-danger-700 active:scale-[0.98]"
            >
                Reintentar todos ({{ $this->failedCount() }})
            </button>
        @endif
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">ID</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Pedido</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Tipo</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Impresora</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Estado</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Intentos</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Error</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Creado</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                @forelse ($this->jobs as $job)
                    <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-800">
                        <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">#{{ $job->getKey() }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                            @if ($job->pedido)
                                {{ $job->pedido->codigo_corto ?? $job->pedido_id }}
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $job->tipo_trabajo?->value === 'COMANDA' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800' }}">
                                {{ $job->tipo_trabajo?->label() ?? $job->tipo_trabajo }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $job->impresora?->nombre ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm">
                            @php
                                $estadoColors = [
                                    'PENDIENTE' => 'bg-gray-100 text-gray-800',
                                    'PROCESANDO' => 'bg-blue-100 text-blue-800',
                                    'IMPRESO' => 'bg-green-100 text-green-800',
                                    'ERROR' => 'bg-red-100 text-red-800',
                                ];
                            @endphp
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $estadoColors[$job->estado->value] ?? 'bg-gray-100 text-gray-800' }}">
                                {{ $job->estado->label() }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $job->intentos }}</td>
                        <td class="max-w-xs truncate px-4 py-3 text-sm text-red-600 dark:text-red-400" title="{{ $job->ultimo_error }}">
                            {{ $job->ultimo_error ?? '—' }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $job->created_at?->diffForHumans() ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-sm">
                            @if ($job->estado === \App\Enums\EstadoImpresion::ERROR)
                                <button
                                    wire:click="retryJob({{ $job->getKey() }})"
                                    class="text-sm font-semibold text-primary-600 transition hover:text-primary-800 dark:text-primary-400"
                                >
                                    Reintentar
                                </button>
                            @elseif ($job->estado === \App\Enums\EstadoImpresion::IMPRESO)
                                <span class="text-sm text-green-600 dark:text-green-400">
                                    {{ $job->impreso_at?->format('H:i') ?? '—' }}
                                </span>
                            @else
                                <span class="text-sm text-gray-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                            No hay trabajos de impresión registrados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
