<?php

namespace App\Filament\Pages\Pos;

use App\Application\Printing\QueueTicketResult;
use App\Application\Printing\ReprintTicket;
use App\Enums\EstadoCocina;
use App\Enums\EstadoLineaPedido;
use App\Enums\TipoPedido;
use App\Filament\Concerns\GuardsEstablishment;
use App\Models\Establecimiento;
use App\Models\Pedido;
use App\Models\TandaPedido;
use App\Services\KitchenService;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class EntregaDisplay extends Page
{
    use GuardsEstablishment;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'entrega';

    protected static ?string $title = 'Entrega · Pedidos listos';

    protected string $view = 'filament.admin.pages.pos.entrega-display';

    public ?string $feedback = null;

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('operar_cocina');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        if (! $this->ensureEstablishment()) {
            return;
        }
    }

    public function markDelivered(int $tandaId): void
    {
        try {
            $tanda = TandaPedido::query()->findOrFail($tandaId);
            app(KitchenService::class)->transition($tanda, EstadoCocina::ENTREGADA, auth()->user());
            $this->feedback = 'Pedido marcado como entregado.';
        } catch (ValidationException $exception) {
            $this->feedback = collect($exception->errors())->flatten()->first()
                ?? 'La tanda ya fue actualizada. Refresca la pantalla.';
        } catch (\Throwable $exception) {
            report($exception);
            $this->feedback = 'No se pudo marcar la entrega. Inténtalo nuevamente.';
        }
    }

    public function reimprimirTicket(int $pedidoId): void
    {
        try {
            $pedido = Pedido::query()
                ->where('establecimiento_id', $this->establishmentId())
                ->findOrFail($pedidoId);
            $result = app(ReprintTicket::class)->handle($pedido, auth()->user());

            $this->feedback = match ($result->status) {
                QueueTicketResult::NO_PRINTER => 'No hay una impresora de ticket configurada.',
                QueueTicketResult::FAILED => $result->message ?? 'No se pudo reimprimir el ticket.',
                default => 'Ticket reimpreso y encolado para imprimir.',
            };
        } catch (\Throwable $exception) {
            report($exception);
            $this->feedback = 'No se pudo reimprimir el ticket.';
        }
    }

    public function getReadyBatchesProperty(): Collection
    {
        return $this->batchesQuery(EstadoCocina::LISTA)
            ->get();
    }

    public function getPreparingBatchesProperty(): Collection
    {
        return $this->batchesQuery(EstadoCocina::EN_PREPARACION)
            ->get();
    }

    public function locationLabel(TandaPedido $tanda): string
    {
        return $tanda->pedido?->mesa
            ? 'Mesa '.$tanda->pedido->mesa->numero
            : TipoPedido::PARA_LLEVAR->label().' · Mostrador';
    }

    public function elapsedLabel(TandaPedido $tanda): string
    {
        $minutes = max(0, (int) $tanda->updated_at?->diffInMinutes(now()));

        return $minutes === 0 ? 'menos de 1 min' : $minutes.' min';
    }

    public function comboLineSummary(array $selection): array
    {
        return collect($selection)
            ->flatMap(fn (array $group): array => collect($group['items'] ?? [])
                ->map(fn (array $item): string => ($group['nombre'] ?? 'Selección').': '.$item['cantidad'].' × '.$item['nombre'])
                ->all())
            ->values()
            ->all();
    }

    private function batchesQuery(EstadoCocina $status): Builder
    {
        return TandaPedido::query()
            ->where('estado_cocina', $status->value)
            ->whereHas('pedido', function (Builder $pedidoQuery): void {
                $pedidoQuery->where('establecimiento_id', $this->establishmentId());
            })
            ->whereHas('detalles', function (Builder $detailQuery): void {
                $detailQuery->where('estado_linea', EstadoLineaPedido::ACTIVA->value);
            })
            ->with([
                'pedido.mesa',
                'detalles.producto',
                'detalles.combo',
            ])
            ->orderBy('created_at')
            ->orderBy('id');
    }

    private function establishmentId(): ?int
    {
        return app(\App\Contracts\EstablishmentContextInterface::class)->idOrNull();
    }
}
