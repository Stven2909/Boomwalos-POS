<?php

namespace App\Filament\Pages\Pos;

use App\Enums\EstadoComercialPedido;
use App\Enums\OrigenPedido;
use App\Enums\TipoPedido;
use App\Filament\Pages\Cash\OpenSession;
use App\Models\Pedido;
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

    private function requestedOrigen(): OrigenPedido
    {
        return request()->query('origen') === 'dispositivo'
            ? OrigenPedido::DISPOSITIVO
            : OrigenPedido::CAJA;
    }
}
