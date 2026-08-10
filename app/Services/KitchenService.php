<?php

namespace App\Services;

use App\Enums\EstadoComercialPedido;
use App\Enums\EstadoCocina;
use App\Enums\EstadoMesa;
use App\Models\EventoAuditoria;
use App\Models\Pedido;
use App\Models\TandaPedido;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KitchenService
{
    public function transition(TandaPedido $tanda, EstadoCocina $destination, User $actor): TandaPedido
    {
        if (! $actor->can('operar_cocina')) {
            throw new AuthorizationException('No tienes permiso para operar la cocina.');
        }

        return DB::transaction(function () use ($tanda, $destination, $actor): TandaPedido {
            $pedido = Pedido::query()
                ->where('establecimiento_id', $this->establishmentId())
                ->lockForUpdate()
                ->findOrFail($tanda->pedido_id);

            $lockedTanda = $pedido->tandas()
                ->lockForUpdate()
                ->findOrFail($tanda->getKey());

            $current = $lockedTanda->estado_cocina;

            if ($current === $destination) {
                return $lockedTanda->fresh($this->tandaRelations());
            }

            if (! $this->isAllowedTransition($current, $destination)) {
                throw ValidationException::withMessages([
                    'tanda' => "No se puede pasar una tanda de {$current->label()} a {$destination->label()}.",
                ]);
            }

            $lockedTanda->update([
                'estado_cocina' => $destination,
            ]);

            $this->auditTanda($lockedTanda, $actor, $this->eventFor($destination), [
                'pedido_id' => $pedido->getKey(),
                'numero_tanda' => $lockedTanda->numero_tanda,
                'estado_anterior' => $current->value,
                'estado_nuevo' => $destination->value,
            ]);

            if ($destination === EstadoCocina::ENTREGADA) {
                $this->closeOrderIfReady($pedido, $actor);
            }

            return $lockedTanda->fresh($this->tandaRelations());
        });
    }

    public function closeOrderIfReady(Pedido $pedido, User $actor): bool
    {
        if ($pedido->estado_comercial !== EstadoComercialPedido::COBRADO) {
            return false;
        }

        $hasUnresolvedBatches = $pedido->tandas()
            ->whereNotIn('estado_cocina', [
                EstadoCocina::ENTREGADA->value,
                EstadoCocina::CANCELADA->value,
            ])
            ->exists();

        if ($hasUnresolvedBatches || ! $pedido->tandas()->exists()) {
            return false;
        }

        $pedido->update([
            'estado_comercial' => EstadoComercialPedido::CERRADO,
        ]);

        if ($pedido->mesa_id) {
            $pedido->mesa()->update([
                'estado' => EstadoMesa::LIBRE,
            ]);
        }

        $this->auditPedido($pedido, $actor, 'pedido_cerrado', [
            'motivo' => 'Todas las tandas activas fueron entregadas.',
        ]);

        return true;
    }

    private function isAllowedTransition(?EstadoCocina $current, EstadoCocina $destination): bool
    {
        return match ($current) {
            EstadoCocina::PENDIENTE => $destination === EstadoCocina::EN_PREPARACION,
            EstadoCocina::EN_PREPARACION => $destination === EstadoCocina::LISTA,
            EstadoCocina::LISTA => $destination === EstadoCocina::ENTREGADA,
            default => false,
        };
    }

    private function eventFor(EstadoCocina $destination): string
    {
        return match ($destination) {
            EstadoCocina::EN_PREPARACION => 'tanda_iniciada_preparacion',
            EstadoCocina::LISTA => 'tanda_marcada_lista',
            EstadoCocina::ENTREGADA => 'tanda_entregada',
            default => 'tanda_actualizada',
        };
    }

    private function auditTanda(TandaPedido $tanda, User $actor, string $type, array $payload): void
    {
        EventoAuditoria::create([
            'entidad_tipo' => TandaPedido::class,
            'entidad_id' => $tanda->getKey(),
            'usuario_id' => $actor->getKey(),
            'tipo_evento' => $type,
            'payload' => $payload,
        ]);
    }

    private function auditPedido(Pedido $pedido, User $actor, string $type, array $payload): void
    {
        EventoAuditoria::create([
            'entidad_tipo' => Pedido::class,
            'entidad_id' => $pedido->getKey(),
            'usuario_id' => $actor->getKey(),
            'tipo_evento' => $type,
            'payload' => $payload,
        ]);
    }

    private function establishmentId(): int
    {
        $id = DB::table('establecimientos')->orderBy('id')->value('id');

        if (! $id) {
            throw ValidationException::withMessages([
                'establecimiento' => 'Configura un establecimiento antes de operar la cocina.',
            ]);
        }

        return (int) $id;
    }

    private function tandaRelations(): array
    {
        return [
            'pedido.mesa',
            'detalles.producto',
            'detalles.combo',
            'detalles.detallePedidoNotas.notaCocina',
        ];
    }
}
