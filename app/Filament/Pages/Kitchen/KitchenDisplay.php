<?php

namespace App\Filament\Pages\Kitchen;

use App\Enums\EstadoCocina;
use App\Enums\EstadoLineaPedido;
use App\Enums\TipoPedido;
use App\Enums\ZonaMesa;
use App\Models\Establecimiento;
use App\Models\TandaPedido;
use App\Services\KitchenService;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class KitchenDisplay extends Page
{
    private const ATTENTION_MINUTES = 10;

    private const LATE_MINUTES = 15;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'cocina';

    protected static ?string $title = 'Cocina · KDS';

    protected string $view = 'filament.admin.pages.kitchen.kitchen-display';

    public string $filter = 'all';

    public string $activeStatus = EstadoCocina::PENDIENTE->value;

    public string $lastUpdatedAt = '';

    public ?string $feedback = null;

    /** @var array<int, int> */
    public array $knownPendingTandaIds = [];

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('operar_cocina');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->lastUpdatedAt = now()->format('H:i:s');
        $this->knownPendingTandaIds = $this->pendingTandaIds();
    }

    public function setFilter(string $filter): void
    {
        if (! collect($this->filterOptions())->contains('value', $filter)) {
            return;
        }

        $this->filter = $filter;
    }

    public function setActiveStatus(string $status): void
    {
        if (! in_array($status, $this->activeStatuses(), true)) {
            return;
        }

        $this->activeStatus = $status;
    }

    public function refreshBoard(): void
    {
        $currentPendingIds = $this->pendingTandaIds();
        $newIds = array_values(array_diff($currentPendingIds, $this->knownPendingTandaIds));

        $this->knownPendingTandaIds = $currentPendingIds;
        $this->lastUpdatedAt = now()->format('H:i:s');
        $this->dispatch('kds-board-updated', at: $this->lastUpdatedAt);

        if ($newIds !== []) {
            $this->dispatch('kds-new-tanda', ids: $newIds);
        }
    }

    public function startPreparation(int $tandaId): void
    {
        $this->performTransition($tandaId, EstadoCocina::EN_PREPARACION, 'La tanda entró en preparación.');
    }

    public function markReady(int $tandaId): void
    {
        $this->performTransition($tandaId, EstadoCocina::LISTA, 'La tanda está lista para entregar.');
    }

    public function markDelivered(int $tandaId): void
    {
        $this->performTransition($tandaId, EstadoCocina::ENTREGADA, 'La tanda fue marcada como entregada.');
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    public function filterOptions(): array
    {
        return [
            ['value' => 'all', 'label' => 'Todos'],
            ['value' => ZonaMesa::SALON->value, 'label' => ZonaMesa::SALON->label()],
            ['value' => ZonaMesa::TERRAZA->value, 'label' => ZonaMesa::TERRAZA->label()],
            ['value' => ZonaMesa::BAR->value, 'label' => ZonaMesa::BAR->label()],
            ['value' => TipoPedido::PARA_LLEVAR->value, 'label' => TipoPedido::PARA_LLEVAR->label()],
        ];
    }

    /**
     * @return array<int, array{status:EstadoCocina,title:string,action:string,actionLabel:string,tone:string}>
     */
    public function columns(): array
    {
        return [
            [
                'status' => EstadoCocina::PENDIENTE,
                'title' => 'Nuevos',
                'action' => 'startPreparation',
                'actionLabel' => 'Iniciar preparación',
                'tone' => 'pending',
            ],
            [
                'status' => EstadoCocina::EN_PREPARACION,
                'title' => 'En preparación',
                'action' => 'markReady',
                'actionLabel' => 'Marcar listo',
                'tone' => 'preparing',
            ],
            [
                'status' => EstadoCocina::LISTA,
                'title' => 'Listos',
                'action' => 'markDelivered',
                'actionLabel' => 'Marcar entregada',
                'tone' => 'ready',
            ],
        ];
    }

    public function statusCounts(): array
    {
        $counts = [];

        foreach ($this->activeStatuses() as $status) {
            $counts[$status] = $this->batchesQuery()
                ->where('estado_cocina', $status)
                ->count();
        }

        return $counts;
    }

    public function filterCount(string $filter): int
    {
        if (! collect($this->filterOptions())->contains('value', $filter)) {
            return 0;
        }

        return $this->batchesQuery($filter)->count();
    }

    public function batchesByStatus(): Collection
    {
        return $this->batches
            ->groupBy(fn (TandaPedido $tanda): string => $tanda->estado_cocina->value);
    }

    public function elapsedLabel(TandaPedido $tanda): string
    {
        $reference = $tanda->estado_cocina === EstadoCocina::PENDIENTE
            ? $tanda->created_at
            : $tanda->updated_at;

        $minutes = max(0, (int) $reference?->diffInMinutes(now()));

        return $minutes === 0 ? 'menos de 1 min' : $minutes . ' min';
    }

    public function elapsedTone(TandaPedido $tanda): string
    {
        $reference = $tanda->estado_cocina === EstadoCocina::PENDIENTE
            ? $tanda->created_at
            : $tanda->updated_at;

        $minutes = max(0, (int) $reference?->diffInMinutes(now()));

        return match (true) {
            $minutes >= self::LATE_MINUTES => 'late',
            $minutes >= self::ATTENTION_MINUTES => 'attention',
            default => 'normal',
        };
    }

    public function locationLabel(TandaPedido $tanda): string
    {
        return $tanda->pedido?->mesa
            ? 'Mesa ' . $tanda->pedido->mesa->numero
            : TipoPedido::PARA_LLEVAR->label();
    }

    public function zoneLabel(TandaPedido $tanda): string
    {
        return $tanda->pedido?->mesa?->zona?->label() ?? 'Mostrador';
    }

    public function comboLineSummary(array $selection): array
    {
        return collect($selection)
            ->flatMap(fn (array $group): array => collect($group['items'] ?? [])
                ->map(fn (array $item): string => ($group['nombre'] ?? 'Selección') . ': ' . $item['cantidad'] . ' × ' . $item['nombre'])
                ->all())
            ->values()
            ->all();
    }

    public function getBatchesProperty(): Collection
    {
        return $this->batchesQuery()
            ->with([
                'pedido.mesa',
                'pedido.pago',
                'detalles.producto',
                'detalles.combo',
                'detalles.detallePedidoNotas.notaCocina',
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    private function performTransition(int $tandaId, EstadoCocina $destination, string $successMessage): void
    {
        try {
            $tanda = TandaPedido::query()->findOrFail($tandaId);
            app(KitchenService::class)->transition($tanda, $destination, auth()->user());
            $this->feedback = $successMessage;
            $this->lastUpdatedAt = now()->format('H:i:s');
        } catch (ValidationException $exception) {
            $this->feedback = collect($exception->errors())->flatten()->first()
                ?? 'La tanda ya fue actualizada. Refresca el tablero.';
        } catch (\Throwable $exception) {
            report($exception);
            $this->feedback = 'No se pudo actualizar la tanda. Inténtalo nuevamente.';
        }
    }

    private function batchesQuery(?string $filter = null): Builder
    {
        $query = TandaPedido::query()
            ->whereIn('estado_cocina', $this->activeStatuses())
            ->whereHas('pedido', function (Builder $pedidoQuery): void {
                $pedidoQuery->where('establecimiento_id', $this->establishmentId());
            })
            ->whereHas('detalles', function (Builder $detailQuery): void {
                $detailQuery->where('estado_linea', EstadoLineaPedido::ACTIVA->value);
            });

        $this->applyFilter($query, $filter ?? $this->filter);

        return $query;
    }

    private function applyFilter(Builder $query, string $filter): void
    {
        if (in_array($filter, [
            ZonaMesa::SALON->value,
            ZonaMesa::TERRAZA->value,
            ZonaMesa::BAR->value,
        ], true)) {
            $query->whereHas('pedido.mesa', function (Builder $mesaQuery) use ($filter): void {
                $mesaQuery->where('zona', $filter);
            });

            return;
        }

        if ($filter === TipoPedido::PARA_LLEVAR->value) {
            $query->whereHas('pedido', function (Builder $pedidoQuery): void {
                $pedidoQuery->where('tipo_pedido', TipoPedido::PARA_LLEVAR->value);
            });
        }
    }

    /** @return array<int, string> */
    private function activeStatuses(): array
    {
        return [
            EstadoCocina::PENDIENTE->value,
            EstadoCocina::EN_PREPARACION->value,
            EstadoCocina::LISTA->value,
        ];
    }

    /** @return array<int, int> */
    private function pendingTandaIds(): array
    {
        return TandaPedido::query()
            ->where('estado_cocina', EstadoCocina::PENDIENTE->value)
            ->whereHas('pedido', function (Builder $pedidoQuery): void {
                $pedidoQuery->where('establecimiento_id', $this->establishmentId());
            })
            ->whereHas('detalles', function (Builder $detailQuery): void {
                $detailQuery->where('estado_linea', EstadoLineaPedido::ACTIVA->value);
            })
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function establishmentId(): int
    {
        return (int) (Establecimiento::query()->orderBy('id')->value('id') ?? 0);
    }
}
