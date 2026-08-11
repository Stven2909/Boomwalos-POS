<?php

namespace App\Filament\Pages\Pos;

use App\Enums\EstadoLineaPedido;
use App\Enums\MetodoPago;
use App\Enums\OrigenPedido;
use App\Models\Pedido;
use App\Services\CobroService;
use Illuminate\Validation\ValidationException;

class ChargeOrder extends PosPage
{
    protected static ?string $slug = 'pos/cobro/{pedido}';

    protected static ?string $title = 'Cobrar cuenta';

    protected string $view = 'filament.admin.pages.pos.charge-order';

    public Pedido $pedido;

    public string $metodoPago = MetodoPago::EFECTIVO->value;

    public string $montoRecibido = '';

    public ?string $feedback = null;

    public static function canAccess(): bool
    {
        return auth()->check()
            && auth()->user()->can('crear_pedido')
            && auth()->user()->can('cobrar_pedido');
    }

    public function mount(Pedido $pedido): void
    {
        if (! $this->ensureCashSession()) {
            return;
        }

        abort_unless($pedido->establecimiento_id === $this->establishment()->getKey(), 404);
        abort_unless($pedido->estado_comercial->isPayable(), 404);

        $this->pedido = $pedido;
        $this->refreshPedido();
    }

    public function backUrl(): string
    {
        return $this->pedido->origen_pedido === OrigenPedido::DISPOSITIVO
            ? ListaPedidos::getUrl()
            : OrderEntry::getUrl(['pedido' => $this->pedido->getKey()]);
    }

    public function updatedMetodoPago(string $metodo): void
    {
        if ($metodo === MetodoPago::TARJETA->value) {
            $this->montoRecibido = number_format($this->total, 2, '.', '');
        }
    }

    public function charge(): void
    {
        try {
            app(CobroService::class)->charge(
                $this->pedido,
                MetodoPago::tryFrom($this->metodoPago) ?? MetodoPago::EFECTIVO,
                $this->metodoPago === MetodoPago::EFECTIVO->value ? $this->montoRecibido : null,
                auth()->user(),
            );

            session()->flash('pos_feedback', 'Pago registrado. El pedido quedó cobrado.');
            $this->redirect(ServiceSelection::getUrl());
        } catch (ValidationException $exception) {
            $this->feedback = collect($exception->errors())->flatten()->first() ?? 'No se pudo registrar el pago.';
        }
    }

    public function getActiveDetailsProperty()
    {
        return $this->pedido->detalles
            ->where('estado_linea', EstadoLineaPedido::ACTIVA)
            ->values();
    }

    public function getTotalProperty(): float
    {
        return (float) $this->activeDetails->sum(
            fn ($detalle): float => (float) $detalle->precio_unitario * (int) $detalle->cantidad,
        );
    }

    public function getChangeProperty(): float
    {
        if ($this->metodoPago !== MetodoPago::EFECTIVO->value || ! is_numeric($this->montoRecibido)) {
            return 0.00;
        }

        return max(0, round((float) $this->montoRecibido - $this->total, 2));
    }

    public function getIsReadyToChargeProperty(): bool
    {
        return $this->activeDetails->isNotEmpty()
            && $this->activeDetails->every(fn ($detalle): bool => $detalle->tanda_id !== null);
    }

    public function comboLineSummary($line): string
    {
        return collect($line->seleccion_combo ?? [])
            ->flatMap(fn (array $group): array => collect($group['items'] ?? [])
                ->map(fn (array $item): string => $item['cantidad'] . ' ' . $item['nombre'])
                ->all())
            ->implode(', ');
    }

    private function refreshPedido(): void
    {
        $this->pedido->refresh()->load([
            'mesa',
            'detalles.producto',
            'detalles.combo',
            'detalles.tanda',
        ]);
    }
}
