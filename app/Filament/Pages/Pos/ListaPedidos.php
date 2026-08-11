<?php

namespace App\Filament\Pages\Pos;

use App\Enums\EstadoComercialPedido;
use App\Enums\EstadoLineaPedido;
use App\Enums\TipoPedido;
use App\Models\Pedido;

class ListaPedidos extends PosPage
{
    protected static ?string $slug = 'pos/pedidos';

    protected static ?string $title = 'Pedidos por cobrar';

    protected string $view = 'filament.admin.pages.pos.lista-pedidos';

    public function mount(): void
    {
        $this->ensureCashSession();
    }

    public function openOrder(int $pedidoId): void
    {
        $this->redirect(ChargeOrder::getUrl(['pedido' => $pedidoId]));
    }

    public function getPendingOrdersProperty()
    {
        return Pedido::query()
            ->where('establecimiento_id', $this->establishment()->getKey())
            ->where('estado_comercial', EstadoComercialPedido::PENDIENTE_COBRO->value)
            ->with(['mesa', 'detalles'])
            ->orderByDesc('id')
            ->get();
    }

    public function orderTotal(Pedido $pedido): float
    {
        return (float) $pedido->detalles
            ->where('estado_linea', EstadoLineaPedido::ACTIVA)
            ->sum(fn ($detalle): float => (float) $detalle->precio_unitario * (int) $detalle->cantidad);
    }

    public function orderContextLabel(Pedido $pedido): string
    {
        return $pedido->tipo_pedido === TipoPedido::MESA
            ? 'Mesa '.$pedido->mesa?->numero
            : 'Para llevar';
    }

    public function orderItemCount(Pedido $pedido): int
    {
        return (int) $pedido->detalles
            ->where('estado_linea', EstadoLineaPedido::ACTIVA)
            ->sum('cantidad');
    }
}
