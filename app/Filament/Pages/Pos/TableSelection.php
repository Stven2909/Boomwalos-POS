<?php

namespace App\Filament\Pages\Pos;

use App\Enums\EstadoComercialPedido;
use App\Enums\EstadoMesa;
use App\Enums\TipoPedido;
use App\Enums\ZonaMesa;
use App\Models\Mesa;
use App\Services\PedidoService;
use Illuminate\Validation\ValidationException;

class TableSelection extends PosPage
{
    protected static ?string $slug = 'pos/mesas';

    protected static ?string $title = 'Elegir mesa';

    protected string $view = 'filament.admin.pages.pos.table-selection';

    public string $zona = ZonaMesa::SALON->value;

    public string $entryMode = 'service';

    public ?int $selectedMesaId = null;

    public ?string $selectedMesaNumero = null;

    public function mount(): void
    {
        if (! $this->ensureCashSession()) {
            return;
        }

        if (request()->query('tipo') !== TipoPedido::MESA->value) {
            $this->redirect(ServiceSelection::getUrl());

            return;
        }

        $zona = ZonaMesa::tryFrom((string) request()->query('zona', ZonaMesa::SALON->value));
        $this->zona = ($zona ?? ZonaMesa::SALON)->value;
        $this->entryMode = request()->query('entrada') === 'mesas' ? 'mesas' : 'service';
    }

    public function setZone(string $zona): void
    {
        $this->zona = (ZonaMesa::tryFrom($zona) ?? ZonaMesa::SALON)->value;
        $this->selectedMesaId = null;
        $this->selectedMesaNumero = null;
    }

    public function selectTable(int $mesaId): void
    {
        $mesa = $this->tables()->firstWhere('id', $mesaId);

        if (! $mesa) {
            return;
        }

        $pedido = $mesa->pedidos->first();

        if ($pedido) {
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

    private function openTable(int $mesaId): void
    {
        $pedido = app(PedidoService::class)->startOrder(
            TipoPedido::MESA,
            auth()->user(),
            $mesaId,
        );

        $this->redirect(OrderEntry::getUrl(['pedido' => $pedido->getKey()]));
    }

    public function getTablesProperty()
    {
        $activeStates = [
            EstadoComercialPedido::ABIERTO->value,
            EstadoComercialPedido::COBRADO->value,
        ];

        return Mesa::query()
            ->where('establecimiento_id', $this->establishment()->getKey())
            ->where('activa', true)
            ->where('zona', $this->zona)
            ->with(['pedidos' => function ($query) use ($activeStates): void {
                $query
                    ->whereIn('estado_comercial', $activeStates)
                    ->latest('id');
            }])
            ->orderBy('numero')
            ->get();
    }

    private function tables()
    {
        return $this->tables;
    }
}
