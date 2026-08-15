<?php

namespace App\Filament\Pages\Pos;

use App\Application\Printing\QueueTicketResult;
use App\Enums\EstadoLineaPedido;
use App\Enums\MetodoPago;
use App\Enums\OrigenPedido;
use App\Models\Pedido;
use App\Services\CobroService;
use App\Services\ConfiguracionService;
use Illuminate\Validation\ValidationException;

class ChargeOrder extends PosPage
{
    protected static ?string $slug = 'pos/cobro/{pedido}';

    protected static ?string $title = 'Cobrar cuenta';

    protected string $view = 'filament.admin.pages.pos.charge-order';

    public Pedido $pedido;

    public string $metodoPago = MetodoPago::EFECTIVO->value;

    public string $montoRecibido = '';

    public bool $tarjetaAprobada = false;

    public string $tarjetaReferencia = '';

    public string $tarjetaTerminal = '';

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

    public function ingresarDigito(string $digito): void
    {
        if ($this->metodoPago !== MetodoPago::EFECTIVO->value) {
            return;
        }

        if (! preg_match('/^[0-9.]$/', $digito)) {
            return;
        }

        $current = $this->montoRecibido;

        if ($digito === '.') {
            if (str_contains($current, '.')) {
                return;
            }

            $this->montoRecibido = $current === '' ? '0.' : $current . '.';

            return;
        }

        $dotPosition = strpos($current, '.');

        if ($dotPosition !== false && strlen(substr($current, $dotPosition + 1)) >= 2) {
            return;
        }

        $this->montoRecibido = $current === '0' ? $digito : $current . $digito;
    }

    public function borrarDigito(): void
    {
        if ($this->metodoPago === MetodoPago::EFECTIVO->value) {
            $this->montoRecibido = mb_substr($this->montoRecibido, 0, -1);
        }
    }

    public function limpiarMonto(): void
    {
        if ($this->metodoPago === MetodoPago::EFECTIVO->value) {
            $this->montoRecibido = '';
        }
    }

    public function usarMontoRapido(string $monto): void
    {
        if ($this->metodoPago === MetodoPago::EFECTIVO->value) {
            $this->montoRecibido = $monto;
        }
    }

    public function usarMontoExacto(): void
    {
        $this->montoRecibido = number_format($this->total, 2, '.', '');
    }

    public function charge(): void
    {
        try {
            $tarjeta = $this->metodoPago === MetodoPago::TARJETA->value
                ? [
                    'aprobada' => $this->tarjetaAprobada,
                    'referencia' => $this->tarjetaReferencia,
                    'terminal' => $this->tarjetaTerminal,
                ]
                : null;

            [, , $ticketResult] = app(CobroService::class)->chargeAndSend(
                $this->pedido,
                MetodoPago::tryFrom($this->metodoPago) ?? MetodoPago::EFECTIVO,
                $this->metodoPago === MetodoPago::EFECTIVO->value ? $this->montoRecibido : null,
                auth()->user(),
                $tarjeta,
            );

            $ticketMessage = match ($ticketResult?->status) {
                QueueTicketResult::NO_PRINTER => ' No se imprimió el ticket de cliente: no hay impresora de ticket configurada.',
                QueueTicketResult::FAILED => ' No se pudo imprimir el ticket de cliente.',
                default => ' Ticket de cliente en cola de impresión.',
            };

            session()->flash('pos_feedback', 'Pago registrado y comanda enviada a cocina.' . $ticketMessage);
            $this->redirect(ServiceSelection::getUrl());
        } catch (ValidationException $exception) {
            $this->feedback = collect($exception->errors())->flatten()->first() ?? 'No se pudo registrar el pago.';
        } catch (\Throwable $exception) {
            $this->feedback = $exception->getMessage();
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
        return $this->activeDetails->isNotEmpty();
    }

    public function getCanSubmitPaymentProperty(): bool
    {
        if (! $this->isReadyToCharge) {
            return false;
        }

        if ($this->metodoPago === MetodoPago::TARJETA->value) {
            return $this->tarjetaAprobada && trim($this->tarjetaReferencia) !== '';
        }

        return true;
    }

    public function getMontosRapidosProperty(): array
    {
        $montos = app(ConfiguracionService::class)->get('pos.montos_rapidos_efectivo', [1, 5, 10, 20, 50]);

        return collect($montos)
            ->filter(fn (mixed $monto): bool => is_numeric($monto))
            ->map(fn (mixed $monto): string => number_format((float) $monto, 2, '.', ''))
            ->values()
            ->all();
    }

    public function getSimboloMonedaProperty(): string
    {
        return (string) app(ConfiguracionService::class)->get('moneda.simbolo', '$');
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
