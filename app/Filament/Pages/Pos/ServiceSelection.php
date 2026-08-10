<?php

namespace App\Filament\Pages\Pos;

use App\Enums\TipoPedido;
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
        $this->redirect(TableSelection::getUrl(['tipo' => TipoPedido::MESA->value]));
    }

    public function selectTakeaway(): void
    {
        if (! $this->ensureCashSession()) {
            return;
        }

        $pedido = app(PedidoService::class)->startOrder(
            TipoPedido::PARA_LLEVAR,
            auth()->user(),
        );

        $this->redirect(OrderEntry::getUrl(['pedido' => $pedido->getKey()]));
    }
}
