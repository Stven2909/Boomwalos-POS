<?php

namespace App\Services;

use App\Application\Fiscal\FiscalOutboxService;
use App\Application\Kitchen\QueueKitchenBatch;
use App\Application\Printing\QueueCustomerTicket;
use App\Enums\EstadoComercialPedido;
use App\Enums\EstadoCocina;
use App\Enums\EstadoLineaPedido;
use App\Enums\MetodoPago;
use App\Models\ConfiguracionFiscal;
use App\Models\EventoAuditoria;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\SesionCaja;
use App\Models\TandaPedido;
use App\Models\User;
use App\Models\VentaFiscalPos;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CobroService
{
    public function chargeAndSend(
        Pedido $pedido,
        MetodoPago $metodo,
        ?string $montoRecibido,
        User $actor,
        ?array $tarjeta = null,
    ): array {
        if (! $actor->can('cobrar_pedido')) {
            throw new AuthorizationException('No tienes permiso para cobrar pedidos.');
        }

        $resultado = DB::transaction(function () use ($pedido, $metodo, $montoRecibido, $actor, $tarjeta): array {
            return $this->applyCharge($pedido, $metodo, $montoRecibido, $actor, sendPendingToKitchen: true, tarjeta: $tarjeta);
        });

        $this->registrarVentaFiscal($resultado[0]);

        return $resultado;
    }

    public function charge(
        Pedido $pedido,
        MetodoPago $metodo,
        ?string $montoRecibido,
        User $actor,
        ?array $tarjeta = null,
    ): Pago {
        if (! $actor->can('cobrar_pedido')) {
            throw new AuthorizationException('No tienes permiso para cobrar pedidos.');
        }

        $pago = DB::transaction(function () use ($pedido, $metodo, $montoRecibido, $actor, $tarjeta): Pago {
            [$pago] = $this->applyCharge($pedido, $metodo, $montoRecibido, $actor, sendPendingToKitchen: false, tarjeta: $tarjeta);

            return $pago;
        });

        $this->registrarVentaFiscal($pago);

        return $pago;
    }

    private function applyCharge(
        Pedido $pedido,
        MetodoPago $metodo,
        ?string $montoRecibido,
        User $actor,
        bool $sendPendingToKitchen,
        ?array $tarjeta = null,
    ): array {
        $establecimientoId = $this->establishmentId();

        $sesionCaja = $this->activeCashSession($establecimientoId);

        $pedido = Pedido::query()
            ->where('establecimiento_id', $establecimientoId)
            ->lockForUpdate()
            ->findOrFail($pedido->getKey());

        if (! $pedido->estado_comercial->isPayable()) {
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

        $pending = $detalles->whereNull('tanda_id');

        if (! $sendPendingToKitchen && $pending->isNotEmpty()) {
            throw ValidationException::withMessages([
                'pago' => 'Envía a cocina los productos pendientes antes de cobrar la cuenta.',
            ]);
        }

        $total = round((float) $detalles->sum(
            fn ($detalle): float => (float) $detalle->precio_unitario * (int) $detalle->cantidad,
        ), 2);

        if ($metodo === MetodoPago::TARJETA) {
            $this->validateCard($tarjeta);
        }

        [$recibido, $cambio] = $this->resolveAmounts($metodo, $montoRecibido, $total);

        $pago = Pago::create([
            'pedido_id' => $pedido->getKey(),
            'sesion_caja_id' => $sesionCaja->getKey(),
            'metodo_pago' => $metodo,
            'monto_recibido' => $recibido,
            'cambio_devuelto' => $cambio,
            'referencia_externa' => $metodo === MetodoPago::TARJETA ? trim((string) ($tarjeta['referencia'] ?? '')) : null,
        ]);

        $pedido->update(['estado_comercial' => EstadoComercialPedido::COBRADO]);

        $this->audit($pedido, $actor, 'pedido_cobrado', [
            'pago_id' => $pago->getKey(),
            'metodo_pago' => $metodo->value,
            'total' => $total,
            'monto_recibido' => $recibido,
            'cambio_devuelto' => $cambio,
            'origen_pedido' => $pedido->origen_pedido?->value,
            'codigo_corto' => $pedido->codigo_corto,
        ]);

        $tanda = null;

        if ($sendPendingToKitchen && $pending->isNotEmpty()) {
            $numeroTanda = ((int) $pedido->tandas()->max('numero_tanda')) + 1;
            $tanda = $pedido->tandas()->create([
                'numero_tanda' => $numeroTanda,
                'estado_cocina' => EstadoCocina::PENDIENTE,
            ]);

            $pedido->detalles()
                ->whereIn('id', $pending->modelKeys())
                ->update(['tanda_id' => $tanda->getKey()]);

            $printJob = app(QueueKitchenBatch::class)->handle($tanda);

            $this->audit($pedido, $actor, $printJob ? 'comanda_en_cola' : 'comanda_sin_impresora', [
                'tanda_id' => $tanda->getKey(),
                'trabajo_impresion_id' => $printJob?->getKey(),
            ]);

            $this->audit($pedido, $actor, 'pedido_enviado_cocina', [
                'tanda_id' => $tanda->getKey(),
                'numero_tanda' => $tanda->numero_tanda,
                'detalles' => $pending->map(fn ($detalle): array => [
                    'id' => $detalle->getKey(),
                    'producto_id' => $detalle->producto_id,
                    'cantidad' => $detalle->cantidad,
                ])->values()->all(),
            ]);
        }

        $ticketResult = $this->queueCustomerTicket($pedido, $pago, $actor);

        app(KitchenService::class)->closeOrderIfReady($pedido, $actor);

        return [$pago->fresh(['pedido']), $tanda, $ticketResult];
    }

    private function registrarVentaFiscal(Pago $pago): void
    {
        $configuracion = ConfiguracionFiscal::query()
            ->where('establecimiento_id', $pago->pedido->establecimiento_id)
            ->where('fiscal_habilitada', true)
            ->first();

        if (! $configuracion) {
            return;
        }

        if (VentaFiscalPos::query()->where('pago_id', $pago->getKey())->exists()) {
            return;
        }

        app(FiscalOutboxService::class)->registrarVenta($pago->pedido, $pago, $configuracion);
    }

    private function queueCustomerTicket(Pedido $pedido, Pago $pago, User $actor): \App\Application\Printing\QueueTicketResult
    {
        $result = app(QueueCustomerTicket::class)->handle($pedido, $pago, $actor);

        $event = match ($result->status) {
            \App\Application\Printing\QueueTicketResult::NO_PRINTER => 'ticket_sin_impresora',
            \App\Application\Printing\QueueTicketResult::FAILED => 'ticket_fallido',
            default => 'ticket_en_cola',
        };

        $this->audit($pedido, $actor, $event, [
            'trabajo_impresion_id' => $result->trabajo?->getKey(),
            'tipo_trabajo' => $result->trabajo?->tipo_trabajo?->value ?? 'TICKET',
            'mensaje' => $result->message,
        ]);

        return $result;
    }
    private function validateCard(?array $tarjeta): void
    {
        if (($tarjeta['aprobada'] ?? false) !== true) {
            throw ValidationException::withMessages([
                'tarjeta' => 'La aprobación del datáfono es obligatoria para cobrar con tarjeta.',
            ]);
        }

        if (trim((string) ($tarjeta['referencia'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'tarjeta' => 'Ingresa la referencia de la transacción del datáfono.',
            ]);
        }
    }

    private function resolveAmounts(MetodoPago $metodo, ?string $montoRecibido, float $total): array
    {
        if ($metodo === MetodoPago::TARJETA) {
            return [$total, 0.00];
        }

        $rawAmount = trim((string) $montoRecibido);

        if ($rawAmount === '' || ! preg_match('/^\d+(\.\d{1,2})?$/', $rawAmount)) {
            throw ValidationException::withMessages([
                'montoRecibido' => 'Ingresa un monto en efectivo válido con hasta dos decimales.',
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

    private function activeCashSession(int $establecimientoId): SesionCaja
    {
        $sesion = SesionCaja::query()
            ->where('establecimiento_id', $establecimientoId)
            ->whereNull('fecha_cierre')
            ->latest('id')
            ->lockForUpdate()
            ->first();

        if (! $sesion) {
            throw ValidationException::withMessages([
                'sesion' => 'No hay una caja activa. Abre un turno antes de cobrar.',
            ]);
        }

        return $sesion;
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
