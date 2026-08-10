<?php

namespace App\Services;

use App\Enums\EstadoComercialPedido;
use App\Enums\EstadoLineaPedido;
use App\Enums\MetodoPago;
use App\Models\EventoAuditoria;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CobroService
{
    public function charge(
        Pedido $pedido,
        MetodoPago $metodo,
        ?string $montoRecibido,
        User $actor,
    ): Pago {
        if (! $actor->can('cobrar_pedido')) {
            throw new AuthorizationException('No tienes permiso para cobrar pedidos.');
        }

        return DB::transaction(function () use ($pedido, $metodo, $montoRecibido, $actor): Pago {
            $pedido = Pedido::query()
                ->where('establecimiento_id', $this->establishmentId())
                ->lockForUpdate()
                ->findOrFail($pedido->getKey());

            if ($pedido->estado_comercial !== EstadoComercialPedido::ABIERTO) {
                throw ValidationException::withMessages([
                    'pago' => 'Este pedido ya fue cobrado o cerrado.',
                ]);
            }

            $detalles = $pedido->detalles()
                ->where('estado_linea', EstadoLineaPedido::ACTIVA->value)
                ->lockForUpdate()
                ->get();

            if ($detalles->isEmpty()) {
                throw ValidationException::withMessages([
                    'pago' => 'No puedes cobrar un pedido sin productos activos.',
                ]);
            }

            if ($detalles->contains(fn ($detalle): bool => $detalle->tanda_id === null)) {
                throw ValidationException::withMessages([
                    'pago' => 'Envía a cocina los productos pendientes antes de cobrar la cuenta.',
                ]);
            }

            $total = round((float) $detalles->sum(
                fn ($detalle): float => (float) $detalle->precio_unitario * (int) $detalle->cantidad,
            ), 2);

            [$recibido, $cambio] = $this->resolveAmounts($metodo, $montoRecibido, $total);

            $pago = Pago::create([
                'pedido_id' => $pedido->getKey(),
                'metodo_pago' => $metodo,
                'monto_recibido' => $recibido,
                'cambio_devuelto' => $cambio,
            ]);

            $pedido->update(['estado_comercial' => EstadoComercialPedido::COBRADO]);

            $this->audit($pedido, $actor, 'pedido_cobrado', [
                'pago_id' => $pago->getKey(),
                'metodo_pago' => $metodo->value,
                'total' => $total,
                'monto_recibido' => $recibido,
                'cambio_devuelto' => $cambio,
            ]);

            app(KitchenService::class)->closeOrderIfReady($pedido, $actor);

            return $pago->fresh(['pedido']);
        });
    }

    private function resolveAmounts(MetodoPago $metodo, ?string $montoRecibido, float $total): array
    {
        if ($metodo === MetodoPago::TARJETA) {
            return [$total, 0.00];
        }

        $rawAmount = trim((string) $montoRecibido);

        if ($rawAmount === '' || ! is_numeric($rawAmount)) {
            throw ValidationException::withMessages([
                'montoRecibido' => 'Ingresa el monto recibido en efectivo.',
            ]);
        }

        $recibido = round((float) $rawAmount, 2);

        if ($recibido < $total) {
            throw ValidationException::withMessages([
                'montoRecibido' => 'El monto recibido no puede ser menor que el total.',
            ]);
        }

        return [$recibido, round($recibido - $total, 2)];
    }

    private function establishmentId(): int
    {
        $id = DB::table('establecimientos')->orderBy('id')->value('id');

        if (! $id) {
            throw ValidationException::withMessages([
                'establecimiento' => 'Configura un establecimiento antes de cobrar pedidos.',
            ]);
        }

        return (int) $id;
    }

    private function audit(Pedido $pedido, User $actor, string $type, array $payload): void
    {
        EventoAuditoria::create([
            'entidad_tipo' => Pedido::class,
            'entidad_id' => $pedido->getKey(),
            'usuario_id' => $actor->getKey(),
            'tipo_evento' => $type,
            'payload' => $payload,
        ]);
    }
}
