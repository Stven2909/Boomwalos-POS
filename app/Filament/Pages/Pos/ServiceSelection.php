<?php

namespace App\Filament\Pages\Pos;

use App\Enums\EstadoComercialPedido;
use App\Enums\OrigenPedido;
use App\Enums\TipoPedido;
use App\Models\Pedido;
use App\Services\PedidoService;

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

    public function selectLocal(): void
    {
        $this->redirect(TableSelection::getUrl([
            'tipo' => TipoPedido::MESA->value,
            'origen' => $this->requestedOrigen()->value,
        ]));
    }

    public function selectTakeaway(): void
    {
        if (! $this->ensureCashSession()) {
            return;
        }

        $pedido = app(PedidoService::class)->startOrder(
            TipoPedido::PARA_LLEVAR,
            auth()->user(),
            null,
            $this->requestedOrigen(),
        );

        $this->redirect(OrderEntry::getUrl(['pedido' => $pedido->getKey()]));
    }

    public function openPendingList(): void
    {
        $this->redirect(ListaPedidos::getUrl());
    }

    public function getPendingCountProperty(): int
    {
        return (int) Pedido::query()
            ->where('establecimiento_id', $this->establishment()->getKey())
            ->where('estado_comercial', EstadoComercialPedido::PENDIENTE_COBRO->value)
            ->count();
    }

    private function requestedOrigen(): OrigenPedido
    {
        return request()->query('origen') === 'dispositivo'
            ? OrigenPedido::DISPOSITIVO
            : OrigenPedido::CAJA;
    }
}
