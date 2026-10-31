<?php

namespace App\Filament\Pages\Pos;

use App\Enums\EstadoComercialPedido;
use App\Enums\EstadoLineaPedido;
use App\Enums\EstadoMesa;
use App\Enums\FlujoPos;
use App\Enums\FlujoUbicacionMesa;
use App\Enums\OrigenPedido;
use App\Enums\TipoPedido;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\UbicacionMesa;
use App\Services\PedidoService;
use App\Services\PoliticaFlujosPos;
use Illuminate\Validation\ValidationException;

class TableSelection extends PosPage
{
    protected static ?string $slug = 'pos/mesas';

    protected static ?string $title = 'Elegir mesa';

    protected string $view = 'filament.admin.pages.pos.table-selection';

    public string $zona = '';

    public string $entryMode = 'service';

    public ?int $selectedMesaId = null;

    public ?string $selectedMesaNumero = null;

    public function mount(): void
    {
        if (! $this->ensureCashSession()) {
            return;
        }

        $politica = app(PoliticaFlujosPos::class);

        if (! $politica->permite(FlujoPos::MESA_POSTPAGO) && ! $politica->permite(FlujoPos::MOSTRADOR_PREPAGO)) {
            session()->flash('pos_feedback', 'Los flujos de mesa y mostrador están deshabilitados para esta sucursal.');
            $this->redirect(ServiceSelection::getUrl());

            return;
        }

        app(PedidoService::class)->discardEmptyDraftsForUser(auth()->user());

        $tipo = (string) request()->query('tipo', TipoPedido::MESA->value);

        if (! in_array($tipo, [TipoPedido::MESA->value, TipoPedido::PARA_LLEVAR->value], true)) {
            $this->redirect(ServiceSelection::getUrl());

            return;
        }

        $available = $this->availableZones;
        $requested = (string) request()->query('zona', '');

        if ($requested === '' && $tipo === TipoPedido::PARA_LLEVAR->value) {
            $llevar = collect($available)->firstWhere('flujo', FlujoUbicacionMesa::LLEVAR->value);
            $requested = $llevar['nombre'] ?? '';
        }

        $names = array_column($available, 'nombre');

        $this->zona = in_array($requested, $names, true) ? $requested : ($available[0]['nombre'] ?? '');
        $this->entryMode = request()->query('entrada') === 'mesas' ? 'mesas' : 'service';
    }

    /**
     * Ubicaciones activas de la sucursal, ordenadas, según los flujos habilitados.
     *
     * A diferencia de la versión anterior, una ubicación aparece aunque todavía
     * no tenga mesas: así el admin puede verla en el POS y el tablero "Llevar"
     * funciona sin mesas.
     *
     * @return array<int, array{nombre: string, flujo: string, etiqueta: string}>
     */
    public function getAvailableZonesProperty(): array
    {
        $politica = app(PoliticaFlujosPos::class);
        $flujos = [];

        if ($politica->permite(FlujoPos::MESA_POSTPAGO)) {
            $flujos[] = FlujoUbicacionMesa::MESAS->value;
        }

        if ($politica->permite(FlujoPos::MOSTRADOR_PREPAGO)) {
            $flujos[] = FlujoUbicacionMesa::LLEVAR->value;
        }

        if ($flujos === []) {
            return [];
        }

        return UbicacionMesa::query()
            ->where('establecimiento_id', $this->establishment()->getKey())
            ->where('activa', true)
            ->whereIn('flujo', $flujos)
            ->orderBy('orden')
            ->get(['nombre', 'flujo'])
            ->map(fn (UbicacionMesa $ubicacion): array => [
                'nombre' => $ubicacion->nombre,
                'flujo' => $ubicacion->flujo->value,
                'etiqueta' => $ubicacion->nombre,
            ])
            ->values()
            ->all();
    }

    public function getCurrentUbicacionProperty(): ?UbicacionMesa
    {
        if ($this->zona === '') {
            return null;
        }

        return UbicacionMesa::query()
            ->where('establecimiento_id', $this->establishment()->getKey())
            ->where('activa', true)
            ->where('nombre', $this->zona)
            ->first();
    }

    public function getEsLlevarProperty(): bool
    {
        return $this->currentUbicacion?->esParaLlevar() ?? false;
    }

    public function setZone(string $zona): void
    {
        $names = array_column($this->availableZones, 'nombre');

        if (! in_array($zona, $names, true)) {
            return;
        }

        $this->zona = $zona;
        $this->selectedMesaId = null;
        $this->selectedMesaNumero = null;
    }

    public function selectTable(int $mesaId): void
    {
        if ($this->esLlevar) {
            return;
        }

        $mesa = $this->tables()->firstWhere('id', $mesaId);

        if (! $mesa) {
            return;
        }

        $pedido = $mesa->pedidos->first();

        if ($pedido) {
            if ($pedido->estado_comercial === EstadoComercialPedido::PENDIENTE_COBRO) {
                $this->redirect(ChargeOrder::getUrl(['pedido' => $pedido->getKey()]));

                return;
            }

            $this->redirect(OrderEntry::getUrl(['pedido' => $pedido->getKey()]));

            return;
        }

        if ($mesa->estado !== EstadoMesa::LIBRE) {
            $this->addError('mesa', 'La mesa no está disponible. Actualiza la pantalla e inténtalo de nuevo.');

            return;
        }

        if ($this->entryMode === 'mesas') {
            try {
                $this->openTable($mesa->getKey());
            } catch (ValidationException $exception) {
                $this->addError('mesa', collect($exception->errors())->flatten()->first() ?? 'No se pudo abrir la mesa.');
            }

            return;
        }

        $this->selectedMesaId = $mesa->getKey();
        $this->selectedMesaNumero = (string) $mesa->numero;
    }

    public function continueWithTable(): void
    {
        if (! $this->selectedMesaId) {
            return;
        }

        try {
            $this->openTable($this->selectedMesaId);
        } catch (ValidationException $exception) {
            $this->addError('mesa', collect($exception->errors())->flatten()->first() ?? 'No se pudo abrir la mesa.');
        }
    }

    /**
     * Abre un pedido nuevo del flujo Mostrador / Para llevar desde el tablero.
     */
    public function startTakeawayOrder(): void
    {
        if (! $this->esLlevar) {
            return;
        }

        try {
            $pedido = app(PedidoService::class)->startOrder(
                TipoPedido::PARA_LLEVAR,
                auth()->user(),
                null,
                $this->requestedOrigen(),
            );

            $this->redirect(OrderEntry::getUrl(['pedido' => $pedido->getKey()]));
        } catch (ValidationException $exception) {
            $this->addError('llevar', collect($exception->errors())->flatten()->first() ?? 'No se pudo iniciar el pedido para llevar.');
        }
    }

    /**
     * Retoma un pedido para llevar abierto o lo manda a cobro si ya lo pidió.
     */
    public function selectTakeawayOrder(int $pedidoId): void
    {
        if (! $this->esLlevar) {
            return;
        }

        $pedido = $this->takeawayOrders->firstWhere('id', $pedidoId);

        if (! $pedido) {
            return;
        }

        if ($pedido->estado_comercial === EstadoComercialPedido::PENDIENTE_COBRO) {
            $this->redirect(ChargeOrder::getUrl(['pedido' => $pedido->getKey()]));

            return;
        }

        $this->redirect(OrderEntry::getUrl(['pedido' => $pedido->getKey()]));
    }

    private function openTable(int $mesaId): void
    {
        $pedido = app(PedidoService::class)->startOrder(
            TipoPedido::MESA,
            auth()->user(),
            $mesaId,
            $this->requestedOrigen(),
        );

        $this->redirect(OrderEntry::getUrl(['pedido' => $pedido->getKey()]));
    }

    private function requestedOrigen(): OrigenPedido
    {
        return request()->query('origen') === 'dispositivo'
            ? OrigenPedido::DISPOSITIVO
            : OrigenPedido::CAJA;
    }

    public function getTablesProperty()
    {
        if ($this->esLlevar || $this->zona === '') {
            return collect();
        }

        return Mesa::query()
            ->where('establecimiento_id', $this->establishment()->getKey())
            ->where('activa', true)
            ->where('zona', $this->zona)
            ->with(['pedidos' => function ($query): void {
                $query
                    ->where(function ($query): void {
                        $query
                            ->whereIn('estado_comercial', [
                                EstadoComercialPedido::PENDIENTE_COBRO->value,
                                EstadoComercialPedido::COBRADO->value,
                            ])
                            ->orWhere(function ($query): void {
                                $query
                                    ->where('estado_comercial', EstadoComercialPedido::ABIERTO->value)
                                    ->whereHas('detalles', fn ($details) => $details->where('estado_linea', EstadoLineaPedido::ACTIVA->value));
                            });
                    })
                    ->latest('id');
            }])
            ->orderBy('numero')
            ->get();
    }

    /**
     * Pedidos para llevar abiertos o pendientes de cobro de la sucursal.
     */
    public function getTakeawayOrdersProperty()
    {
        if (! $this->esLlevar) {
            return collect();
        }

        return Pedido::query()
            ->where('establecimiento_id', $this->establishment()->getKey())
            ->where('tipo_pedido', TipoPedido::PARA_LLEVAR->value)
            ->whereIn('estado_comercial', [
                EstadoComercialPedido::ABIERTO->value,
                EstadoComercialPedido::PENDIENTE_COBRO->value,
            ])
            ->whereHas('detalles', fn ($query) => $query->where('estado_linea', EstadoLineaPedido::ACTIVA->value))
            ->with(['detalles' => fn ($query) => $query->where('estado_linea', EstadoLineaPedido::ACTIVA->value)])
            ->latest('id')
            ->get();
    }

    private function tables()
    {
        return $this->tables;
    }
}
