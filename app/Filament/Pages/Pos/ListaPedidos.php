<?php

namespace App\Filament\Pages\Pos;

use App\Enums\EstadoComercialPedido;
use App\Enums\EstadoLineaPedido;
use App\Enums\TipoPedido;
use App\Models\Pedido;
use App\Services\PedidoService;

class ListaPedidos extends PosPage
{
    protected static ?string $slug = 'pos/pedidos';

    protected static ?string $title = 'Pedidos';

    protected string $view = 'filament.admin.pages.pos.lista-pedidos';

    public string $filtro = 'pendientes';

    public string $search = '';

    public ?string $feedback = null;

    public function mount(): void
    {
        if (! $this->ensureCashSession()) {
            return;
        }

        $filtro = (string) request()->query('filtro', 'pendientes');

        if (in_array($filtro, ['abiertos', 'pendientes', 'todos'], true)) {
            $this->filtro = $filtro;
        }
    }

    public function setFiltro(string $filtro): void
    {
        if (in_array($filtro, ['abiertos', 'pendientes', 'todos'], true)) {
            $this->filtro = $filtro;
        }
    }

    public function openOrder(int $pedidoId): void
    {
        $this->redirect(ChargeOrder::getUrl(['pedido' => $pedidoId]));
    }

    public function openComanda(int $pedidoId): void
    {
        $this->redirect(OrderEntry::getUrl(['pedido' => $pedidoId]));
    }

    public function cancelarPedido(int $pedidoId): void
    {
        try {
            $pedido = Pedido::query()->findOrFail($pedidoId);
            app(PedidoService::class)->cancelOrder($pedido, auth()->user());
            $this->feedback = 'Pedido cancelado y registrado en auditoría.';
        } catch (\Throwable $exception) {
            $this->feedback = $exception->getMessage();
        }
    }

    public function getOrdersProperty()
    {
        $states = match ($this->filtro) {
            'abiertos' => [EstadoComercialPedido::ABIERTO->value],
            'pendientes' => [EstadoComercialPedido::PENDIENTE_COBRO->value],
            default => [EstadoComercialPedido::ABIERTO->value, EstadoComercialPedido::PENDIENTE_COBRO->value],
        };

        $code = preg_replace('/\D/', '', trim($this->search));
        $search = trim($this->search);

        return Pedido::query()
            ->where('establecimiento_id', $this->establishment()->getKey())
            ->whereIn('estado_comercial', $states)
            ->when($code !== '', fn ($query) => $query->where('codigo_corto', (int) $code))
            ->when($code === '' && $search !== '', fn ($query) => $query->where(function ($inner) use ($search) {
                $inner->where('numero_seguimiento', 'like', '%'.$search.'%')
                    ->orWhere('origen_pedido', 'like', '%'.$search.'%')
                    ->orWhereHas('mesa', fn ($mesaQuery) => $mesaQuery->where('numero', 'like', '%'.$search.'%'));
            }))
            ->with(['mesa', 'usuario', 'detalles'])
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
            : 'Mostrador';
    }

    public function orderItemCount(Pedido $pedido): int
    {
        return (int) $pedido->detalles
            ->where('estado_linea', EstadoLineaPedido::ACTIVA)
            ->sum('cantidad');
    }

    public function estadoLabel(Pedido $pedido): string
    {
        return $pedido->estado_comercial?->label() ?? '';
    }

    public function timeLabel(Pedido $pedido): string
    {
        return $pedido->created_at?->format('H:i') ?? '';
    }
}
