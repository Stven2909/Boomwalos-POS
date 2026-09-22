<x-filament-panels::page>
    <div class="bw-ti-page">
        <header class="bw-ti-hero">
            <div>
                <span class="bw-ti-kicker">CONFIGURACIÓN POR SUCURSAL</span>
                <h1>Operación del POS</h1>
                <p>Define cómo se toman y cobran los pedidos sin alterar ventas, caja ni documentos existentes.</p>
            </div>
            <div class="bw-ti-branch">
                <x-heroicon-o-building-storefront class="h-6 w-6" />
                <span>Sucursal activa</span>
                <strong>{{ $this->establishment->nombre }}</strong>
            </div>
        </header>

        @if ($feedback)
            <div class="bw-ti-alert" role="alert">
                <x-heroicon-o-exclamation-triangle class="h-5 w-5" />
                <span>{{ $feedback }}</span>
            </div>
        @endif

        <div class="bw-ti-layout">
            <main class="bw-ti-main">
                <section class="bw-ti-section" aria-labelledby="flows-title">
                    <div class="bw-ti-section-heading">
                        <div>
                            <h2 id="flows-title">Flujos habilitados</h2>
                            <p>Los accesos del POS se muestran según esta selección.</p>
                        </div>
                        <span class="bw-ti-required">Al menos uno activo</span>
                    </div>

                    <div class="bw-ti-flow-list">
                        <button type="button" wire:click="toggleMostrador" class="bw-ti-flow {{ $mostradorPrepago ? 'is-enabled' : '' }}" aria-pressed="{{ $mostradorPrepago ? 'true' : 'false' }}">
                            <span class="bw-ti-flow-icon"><x-heroicon-o-shopping-bag class="h-7 w-7" /></span>
                            <span class="bw-ti-flow-copy">
                                <strong>Mostrador — cobrar antes</strong>
                                <small>Tomar pedido, cobrar y enviar únicamente los productos pendientes a cocina.</small>
                            </span>
                            <span class="bw-ti-switch" aria-hidden="true"><i></i></span>
                        </button>

                        <button type="button" wire:click="toggleMesa" class="bw-ti-flow {{ $mesaPostpago ? 'is-enabled' : '' }}" aria-pressed="{{ $mesaPostpago ? 'true' : 'false' }}">
                            <span class="bw-ti-flow-icon"><x-heroicon-o-table-cells class="h-7 w-7" /></span>
                            <span class="bw-ti-flow-copy">
                                <strong>Mesa — cobrar después</strong>
                                <small>Enviar comandas, mantener la mesa ocupada y solicitar la cuenta al terminar.</small>
                            </span>
                            <span class="bw-ti-switch" aria-hidden="true"><i></i></span>
                        </button>
                    </div>

                    <label class="bw-ti-default-field">
                        <span>Flujo predeterminado</span>
                        <select wire:model="predeterminado">
                            @if ($mostradorPrepago)
                                <option value="MOSTRADOR_PREPAGO">Mostrador — cobrar antes</option>
                            @endif
                            @if ($mesaPostpago)
                                <option value="MESA_POSTPAGO">Mesa — cobrar después</option>
                            @endif
                        </select>
                        <small>Define cuál opción tendrá mayor énfasis visual; no concede permisos adicionales.</small>
                    </label>
                </section>

                <section class="bw-ti-section" aria-labelledby="gaveta-title">
                    <div class="bw-ti-section-heading">
                        <div>
                            <h2 id="gaveta-title">Gaveta de dinero / Registradora</h2>
                            <p>Controla la apertura y cierre del cajón en el flujo del turno.</p>
                        </div>
                    </div>

                    <div class="bw-ti-flow-list">
                        <button type="button" wire:click="toggleGavetaAuto" class="bw-ti-flow {{ $gavetaAuto ? 'is-enabled' : '' }}" aria-pressed="{{ $gavetaAuto ? 'true' : 'false' }}">
                            <span class="bw-ti-flow-icon"><x-heroicon-o-banknotes class="h-7 w-7" /></span>
                            <span class="bw-ti-flow-copy">
                                <strong>Pulso automático</strong>
                                <small>Al abrir o cerrar turno se pide a la impresora Ticket que abra el cajón (ESC/POS). Requiere una impresora configurada.</small>
                            </span>
                            <span class="bw-ti-switch" aria-hidden="true"><i></i></span>
                        </button>

                        <button type="button" wire:click="toggleGavetaExigir" class="bw-ti-flow {{ $gavetaExigir ? 'is-enabled' : '' }}" aria-pressed="{{ $gavetaExigir ? 'true' : 'false' }}">
                            <span class="bw-ti-flow-icon"><x-heroicon-o-key class="h-7 w-7" /></span>
                            <span class="bw-ti-flow-copy">
                                <strong>Pedir confirmación física</strong>
                                <small>Los formularios de apertura y cierre exigen confirmar que el cajón quedó cerrado o el monto acomodado.</small>
                            </span>
                            <span class="bw-ti-switch" aria-hidden="true"><i></i></span>
                        </button>
                    </div>
                </section>

                <section class="bw-ti-section" aria-labelledby="history-title">
                    <div class="bw-ti-section-heading">
                        <div>
                            <h2 id="history-title">Historial de cambios</h2>
                            <p>Últimas actualizaciones registradas para esta sucursal.</p>
                        </div>
                    </div>

                    <div class="bw-ti-history">
                        @forelse ($this->history as $event)
                            <article>
                                <x-heroicon-o-clock class="h-5 w-5" />
                                <div>
                                    <strong>{{ $event->usuario?->getFilamentName() ?? 'Usuario' }}</strong>
                                    <span>{{ $event->created_at?->format('d/m/Y H:i') }}</span>
                                    <small>
                                        Mostrador: {{ data_get($event->payload, 'nuevo.mostrador_prepago') ? 'Activo' : 'Inactivo' }} ·
                                        Mesa: {{ data_get($event->payload, 'nuevo.mesa_postpago') ? 'Activo' : 'Inactivo' }}
                                    </small>
                                </div>
                            </article>
                        @empty
                            <div class="bw-ti-empty">Aún no se han realizado cambios desde este módulo.</div>
                        @endforelse
                    </div>
                </section>
            </main>

            <aside class="bw-ti-side">
                <section class="bw-ti-section">
                    <h2>Estado de dependencias</h2>
                    <div class="bw-ti-status-list">
                        <a href="{{ $this->tablesUrl() }}">
                            <x-heroicon-o-table-cells class="h-5 w-5" />
                            <span><strong>Mesas</strong><small>{{ $this->dependencies['mesas'] }} activas</small></span>
                        </a>
                        <a href="{{ $this->printerUrl() }}">
                            <x-heroicon-o-printer class="h-5 w-5" />
                            <span><strong>Comanda</strong><small>{{ $this->dependencies['comanda'] ? 'Configurada' : 'Sin impresora' }}</small></span>
                            <i class="{{ $this->dependencies['comanda'] ? 'is-ok' : 'is-warning' }}"></i>
                        </a>
                        <a href="{{ $this->printerUrl() }}">
                            <x-heroicon-o-receipt-percent class="h-5 w-5" />
                            <span><strong>Ticket</strong><small>{{ $this->dependencies['ticket'] ? 'Configurado' : 'Sin impresora' }}</small></span>
                            <i class="{{ $this->dependencies['ticket'] ? 'is-ok' : 'is-warning' }}"></i>
                        </a>
                        <a href="{{ $this->fiscalUrl() }}">
                            <x-heroicon-o-document-check class="h-5 w-5" />
                            <span><strong>Facturación</strong><small>{{ $this->dependencies['fiscal'] ? 'Habilitada' : 'No habilitada' }}</small></span>
                            <i class="{{ $this->dependencies['fiscal'] ? 'is-ok' : 'is-neutral' }}"></i>
                        </a>
                        <div>
                            <x-heroicon-o-wallet class="h-5 w-5" />
                            <span><strong>Caja</strong><small>{{ $this->dependencies['caja'] ? 'Turno activo' : 'Sin turno abierto' }}</small></span>
                            <i class="{{ $this->dependencies['caja'] ? 'is-ok' : 'is-neutral' }}"></i>
                        </div>
                    </div>
                </section>

                <section class="bw-ti-save-panel">
                    <div>
                        <strong>Configuración a aplicar</strong>
                        <span>{{ $mostradorPrepago ? 'Mostrador activo' : 'Mostrador inactivo' }}</span>
                        <span>{{ $mesaPostpago ? 'Mesa activa' : 'Mesa inactiva' }}</span>
                        <span>Gaveta: {{ $gavetaAuto ? 'Pulso automático' : 'Apertura manual' }}{{ $gavetaExigir ? ' · confirmación' : '' }}</span>
                    </div>
                    <button type="button" wire:click="save" wire:confirm="¿Aplicar esta configuración a la sucursal activa? Si existen operaciones incompatibles, el sistema bloqueará el cambio." wire:loading.attr="disabled" class="bw-ti-save">
                        <x-heroicon-o-shield-check class="h-5 w-5" />
                        <span wire:loading.remove>Guardar configuración</span>
                        <span wire:loading>Validando…</span>
                    </button>
                </section>
            </aside>
        </div>
    </div>
</x-filament-panels::page>
