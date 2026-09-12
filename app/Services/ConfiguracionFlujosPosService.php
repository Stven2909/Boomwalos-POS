<?php

namespace App\Services;

use App\Contracts\AuditLoggerInterface;
use App\Contracts\EstablishmentContextInterface;
use App\Enums\EstadoComercialPedido;
use App\Enums\EstadoLineaPedido;
use App\Enums\EstadoMesa;
use App\Enums\TipoPedido;
use App\Models\Configuracion;
use App\Models\Establecimiento;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\User;
use App\ValueObjects\ConfiguracionFlujosPos;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ConfiguracionFlujosPosService
{
    public function __construct(
        private readonly EstablishmentContextInterface $establishmentContext,
        private readonly ConfiguracionService $configuracionService,
        private readonly AuditLoggerInterface $auditLogger,
        private readonly PedidoService $pedidoService,
    ) {}

    public function actualizar(array $value, User $actor): ConfiguracionFlujosPos
    {
        if (! $actor->can('gestionar_configuracion_pos')) {
            throw new AuthorizationException('No tienes permiso para configurar la operación del POS.');
        }

        try {
            $next = ConfiguracionFlujosPos::fromArray($value);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['flujos' => $exception->getMessage()]);
        }

        $establishmentId = $this->establishmentContext->id();

        $result = DB::transaction(function () use ($next, $actor, $establishmentId): ConfiguracionFlujosPos {
            $establishment = Establecimiento::query()->lockForUpdate()->findOrFail($establishmentId);
            $record = Configuracion::query()
                ->where('establecimiento_id', $establishmentId)
                ->where('clave', PoliticaFlujosPos::CONFIG_KEY)
                ->lockForUpdate()
                ->first();

            try {
                $current = $record
                    ? ConfiguracionFlujosPos::fromArray($record->valor ?? [])
                    : ConfiguracionFlujosPos::defaults();
            } catch (\InvalidArgumentException $exception) {
                throw ValidationException::withMessages([
                    'flujos' => 'La configuración guardada es inválida. '.$exception->getMessage(),
                ]);
            }

            if (($current->mostradorPrepago && ! $next->mostradorPrepago)
                || ($current->mesaPostpago && ! $next->mesaPostpago)) {
                $this->pedidoService->discardEmptyDraftsForEstablishment($actor);
            }

            $this->assertCanDisable($current, $next, $establishmentId);

            Configuracion::query()->updateOrCreate(
                ['establecimiento_id' => $establishmentId, 'clave' => PoliticaFlujosPos::CONFIG_KEY],
                ['valor' => $next->toArray()],
            );

            $this->auditLogger->recordEntity($establishment, $actor, 'configuracion_flujos_pos_actualizada', [
                'anterior' => $current->toArray(),
                'nuevo' => $next->toArray(),
            ]);

            return $next;
        }, 3);

        $this->configuracionService->forget(PoliticaFlujosPos::CONFIG_KEY);

        return $result;
    }

    private function assertCanDisable(
        ConfiguracionFlujosPos $current,
        ConfiguracionFlujosPos $next,
        int $establishmentId,
    ): void {
        $blocked = [];

        if ($current->mostradorPrepago && ! $next->mostradorPrepago) {
            $count = $this->activeOrders($establishmentId, TipoPedido::PARA_LLEVAR)->count();

            if ($count > 0) {
                $blocked[] = "{$count} pedido(s) de mostrador activo(s)";
            }
        }

        if ($current->mesaPostpago && ! $next->mesaPostpago) {
            $orders = $this->activeOrders($establishmentId, TipoPedido::MESA)->count();
            $orphanTables = Mesa::query()
                ->where('establecimiento_id', $establishmentId)
                ->where('estado', EstadoMesa::OCUPADA->value)
                ->whereDoesntHave('pedidos', fn (Builder $query): Builder => $query->whereIn('estado_comercial', [
                    EstadoComercialPedido::ABIERTO->value,
                    EstadoComercialPedido::PENDIENTE_COBRO->value,
                    EstadoComercialPedido::COBRADO->value,
                ]))
                ->count();

            if ($orders > 0) {
                $blocked[] = "{$orders} cuenta(s) de mesa activa(s)";
            }

            if ($orphanTables > 0) {
                $blocked[] = "{$orphanTables} mesa(s) ocupada(s) sin cuenta activa";
            }
        }

        if ($blocked !== []) {
            throw ValidationException::withMessages([
                'flujos' => 'No se puede desactivar el flujo: '.implode(' y ', $blocked).'. Resuelve esas operaciones desde Pedidos o Mesas e inténtalo de nuevo.',
            ]);
        }
    }

    private function activeOrders(int $establishmentId, TipoPedido $type): Builder
    {
        return Pedido::query()
            ->where('establecimiento_id', $establishmentId)
            ->where('tipo_pedido', $type->value)
            ->whereIn('estado_comercial', [
                EstadoComercialPedido::ABIERTO->value,
                EstadoComercialPedido::PENDIENTE_COBRO->value,
            ])
            ->where(function (Builder $query): void {
                $query
                    ->whereHas('detalles', fn (Builder $details): Builder => $details->where('estado_linea', EstadoLineaPedido::ACTIVA->value))
                    ->orWhereHas('pago');
            });
    }
}
