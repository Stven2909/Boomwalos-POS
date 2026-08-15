<?php

namespace App\Filament\Pages\Pos;

use App\Enums\EstadoComercialPedido;
use App\Enums\OrigenPedido;
use App\Enums\TipoPedido;
use App\Filament\Pages\Cash\OpenSession;
use App\Models\Pago;
use App\Models\Pedido;
use App\Services\ConfiguracionService;
use App\Services\PedidoService;
use Illuminate\Validation\ValidationException;

class ServiceSelection extends PosPage
{
    protected static ?string $slug = 'pos/servicio';

    protected static ?string $title = 'Punto de Venta';

    protected string $view = 'filament.admin.pages.pos.service-selection';

    public ?string $feedback = null;

    public function mount(): void
    {
        $this->ensureCashSession();
        $this->feedback = session('pos_feedback');
    }

    public function startNewOrder(): void
    {
        if (! $this->ensureCashSession()) {
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
            $this->feedback = collect($exception->errors())->flatten()->first() ?? 'No se pudo iniciar el pedido.';
        }
    }

    public function openOrderSearch(): void
    {
        $this->redirect(ListaPedidos::getUrl(['filtro' => 'abiertos']));
    }

    public function openTables(): void
    {
        $this->redirect(TableSelection::getUrl([
            'tipo' => TipoPedido::MESA->value,
            'entrada' => 'mesas',
        ]));
    }

    public function openPendingList(): void
    {
        $this->redirect(ListaPedidos::getUrl(['filtro' => 'pendientes']));
    }

    public function openCashState(): void
    {
        if ($this->activeCashSession()) {
            return;
        }

        $this->redirect(OpenSession::getUrl());
    }

    public function getPendingCountProperty(): int
    {
        return (int) Pedido::query()
            ->where('establecimiento_id', $this->establishment()->getKey())
            ->where('estado_comercial', EstadoComercialPedido::PENDIENTE_COBRO->value)
            ->count();
    }

    public function getOpenCountProperty(): int
    {
        return (int) Pedido::query()
            ->where('establecimiento_id', $this->establishment()->getKey())
            ->where('estado_comercial', EstadoComercialPedido::ABIERTO->value)
            ->count();
    }

    public function getCashStateProperty(): ?array
    {
        $session = $this->activeCashSession();

        if (! $session) {
            return null;
        }

        return [
            'cajero' => $session->usuarioApertura?->getFilamentName() ?? 'Cajero',
            'monto_inicial' => (float) $session->monto_inicial,
            'fecha_apertura' => $session->fecha_apertura,
        ];
    }

    public function getTurnoSalesProperty(): ?string
    {
        $session = $this->activeCashSession();

        return $session
            ? $this->netoSales(Pago::query()->where('sesion_caja_id', $session->getKey()))
            : null;
    }

    public function getDaySalesProperty(): string
    {
        return $this->netoSales(
            Pago::query()
                ->whereHas('sesionCaja', fn ($query) => $query->where('establecimiento_id', $this->establishment()->getKey()))
                ->whereDate('created_at', now()->toDateString()),
        );
    }

    public function getCashAlertsProperty(): array
    {
        $session = $this->activeCashSession();

        if (! $session) {
            return [[
                'tipo' => 'error',
                'titulo' => 'No hay turno abierto',
                'mensaje' => 'Abre la caja para comenzar a registrar ventas.',
            ]];
        }

        if ($session->fecha_apertura?->startOfDay()->lt(now()->startOfDay())) {
            return [[
                'tipo' => 'warning',
                'titulo' => 'Turno sin cerrar',
                'mensaje' => 'El turno abierto es de un día anterior. Ciérralo para operar con una caja limpia.',
            ]];
        }

        return [];
    }

    public function getSimboloMonedaProperty(): string
    {
        return (string) app(ConfiguracionService::class)->get('moneda.simbolo', '$');
    }

    private function netoSales($query): string
    {
        $row = $query->selectRaw(
            'COALESCE(SUM(monto_recibido), 0) as monto, COALESCE(SUM(cambio_devuelto), 0) as cambio',
        )->first();

        return bcsub((string) $row->monto, (string) $row->cambio, 2);
    }

    private function requestedOrigen(): OrigenPedido
    {
        return request()->query('origen') === 'dispositivo'
            ? OrigenPedido::DISPOSITIVO
            : OrigenPedido::CAJA;
    }
}
