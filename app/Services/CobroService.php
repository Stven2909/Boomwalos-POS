<?php

namespace App\Services;

use App\Contracts\AuditLoggerInterface;
use App\Contracts\CustomerTicketDispatcherInterface;
use App\Contracts\EstablishmentContextInterface;
use App\Contracts\KitchenDispatcherInterface;
use App\Enums\EstadoComercialPedido;
use App\Enums\EstadoCocina;
use App\Enums\EstadoLineaPedido;
use App\Enums\MetodoPago;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\SesionCaja;
use App\Models\TandaPedido;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CobroService
{
    public function __construct(
        private readonly EstablishmentContextInterface $establishmentContext,
        private readonly KitchenDispatcherInterface $kitchenDispatcher,
        private readonly CustomerTicketDispatcherInterface $customerTicketDispatcher,
        private readonly AuditLoggerInterface $auditLogger,
        private readonly FiscalSaleRegistrar $fiscalSaleRegistrar,
        private readonly KitchenService $kitchenService,
    ) {}

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
            'referencia_externa' => $metodo === MetodoPago::TARJETA ? $this->generateInternalPaymentReference($pedido) : null,
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

            $printJob = $this->kitchenDispatcher->dispatch($tanda);

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

        $this->kitchenService->closeOrderIfReady($pedido, $actor);

        return [$pago->fresh(['pedido']), $tanda, $ticketResult];
    }

    private function registrarVentaFiscal(Pago $pago): void
    {
        $this->fiscalSaleRegistrar->register($pago);
    }

    private function queueCustomerTicket(Pedido $pedido, Pago $pago, User $actor): \App\Application\Printing\QueueTicketResult
    {
        $result = $this->customerTicketDispatcher->dispatch($pedido, $pago, $actor);

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
        return $this->establishmentContext->id();
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
        $this->auditLogger->record($pedido, $actor, $type, $payload);
    }

    /**
     * Placeholder: genera un correlativo interno (REF-{YYMMDD}-{codigo_corto}).
     * Si se integra datáfono/pasarela real en el futuro (pendiente de definir en
     * spec), este método se reemplaza por la llamada al gateway — no requiere
     * cambiar la firma ni el resto del flujo de cobro.
     */
    private function generateInternalPaymentReference(Pedido $pedido): string
    {
        $fecha = now()->format('ymd');
        $codigo = $pedido->codigo_corto ?? rand(1000, 9999);

        return "REF-{$fecha}-{$codigo}";
    }
}
