<?php

namespace App\Services;

use App\Application\Printing\QueueTicketResult;
use App\Application\Printing\RenderCustomerTicket;
use App\Application\Printing\RenderKitchenComanda;
use App\Contracts\AuditLoggerInterface;
use App\Contracts\EstablishmentContextInterface;
use App\Enums\EstadoComercialPedido;
use App\Enums\EstadoImpresion;
use App\Enums\EstadoLineaPedido;
use App\Enums\EstadoMesa;
use App\Enums\MetodoPago;
use App\Enums\TipoImpresora;
use App\Enums\TipoPedido;
use App\Enums\TipoTrabajoImpresion;
use App\Jobs\ProcessPrintJob;
use App\Models\Impresora;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\SesionCaja;
use App\Models\TrabajoImpresion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CobroService
{
    public function __construct(
        private readonly EstablishmentContextInterface $establishmentContext,
        private readonly AuditLoggerInterface $auditLogger,
        private readonly FiscalSaleRegistrar $fiscalSaleRegistrar,
        private readonly RenderKitchenComanda $comandaRenderer,
        private readonly RenderCustomerTicket $ticketRenderer,
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

        [$pago, $comandaJob, $ticketJob] = DB::transaction(function () use ($pedido, $metodo, $montoRecibido, $actor, $tarjeta): array {
            return $this->applyCharge($pedido, $metodo, $montoRecibido, $actor, tarjeta: $tarjeta);
        });

        $this->registrarVentaFiscal($pago);

        if ($comandaJob && $comandaJob->estado === EstadoImpresion::PENDIENTE) {
            ProcessPrintJob::dispatch($comandaJob->getKey())->afterCommit();
        }

        if ($ticketJob && $ticketJob->estado === EstadoImpresion::PENDIENTE) {
            ProcessPrintJob::dispatch($ticketJob->getKey())->afterCommit();
        }

        $ticketResult = match (true) {
            $ticketJob === null || ($ticketJob->estado === EstadoImpresion::ERROR && str_contains((string) $ticketJob->ultimo_error, 'no hay impresora')) => QueueTicketResult::noPrinter(),
            $ticketJob->estado === EstadoImpresion::ERROR => QueueTicketResult::failed($ticketJob->ultimo_error ?? 'Error desconocido'),
            default => QueueTicketResult::created($ticketJob),
        };

        return [$pago, $comandaJob, $ticketResult];
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

        [$pago] = DB::transaction(function () use ($pedido, $metodo, $montoRecibido, $actor, $tarjeta): array {
            return $this->applyCharge($pedido, $metodo, $montoRecibido, $actor, tarjeta: $tarjeta);
        });

        $this->registrarVentaFiscal($pago);

        return $pago;
    }

    private function applyCharge(
        Pedido $pedido,
        MetodoPago $metodo,
        ?string $montoRecibido,
        User $actor,
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
            ->where('estado_linea', EstadoLineaPedido::ACTIVA)
            ->lockForUpdate()
            ->get();

        if ($detalles->isEmpty()) {
            throw ValidationException::withMessages([
                'pago' => 'No puedes cobrar un pedido sin productos activos.',
            ]);
        }

        $pendingLines = $detalles->whereNull('tanda_id')->values();

        if ($pedido->tipo_pedido === TipoPedido::MESA) {
            if ($pedido->estado_comercial !== EstadoComercialPedido::PENDIENTE_COBRO) {
                throw ValidationException::withMessages([
                    'pago' => 'Solicita la cuenta de la mesa antes de cobrarla.',
                ]);
            }

            if ($pendingLines->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'pago' => 'Hay productos de la mesa pendientes de enviar a cocina.',
                ]);
            }
        }

        if ($metodo === MetodoPago::TARJETA) {
            $this->validateCard($tarjeta);
        }

        $total = round((float) $detalles->sum(
            fn ($detalle): float => (float) $detalle->precio_unitario * (int) $detalle->cantidad,
        ), 2);

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

        $comandaJob = $this->createComandaJob($pedido, $pendingLines);
        $ticketJob = $this->createTicketJob($pedido, $pago, $actor);

        if ($comandaJob) {
            $this->audit($pedido, $actor, 'comanda_en_cola', [
                'trabajo_impresion_id' => $comandaJob->getKey(),
            ]);
        }
        $this->audit($pedido, $actor, 'ticket_en_cola', [
            'trabajo_impresion_id' => $ticketJob?->getKey(),
        ]);

        $pedido->update(['estado_comercial' => EstadoComercialPedido::CERRADO]);

        if ($pedido->mesa_id) {
            $pedido->mesa()->update(['estado' => EstadoMesa::LIBRE]);
        }

        $this->audit($pedido, $actor, 'pedido_cerrado', [
            'motivo' => 'Cobro completado. Mesa liberada.',
        ]);

        return [$pago->fresh(['pedido']), $comandaJob, $ticketJob];
    }

    private function createComandaJob(Pedido $pedido, Collection $pendingLines): ?TrabajoImpresion
    {
        if ($pendingLines->isEmpty()) {
            return null;
        }

        $printer = Impresora::buscar(TipoImpresora::COMANDA, $pedido->establecimiento_id);
        $contenido = $this->comandaRenderer->render($pedido, $pendingLines);
        $lineIds = $pendingLines->pluck('id')->map(fn ($id): int => (int) $id)->all();
        sort($lineIds);
        $uid = hash('sha256', $pedido->getKey().'|COMANDA|'.implode(',', $lineIds));

        $job = TrabajoImpresion::firstOrCreate(
            ['original_uid' => $uid],
            [
                'impresora_id' => $printer?->getKey(),
                'pedido_id' => $pedido->getKey(),
                'tipo_trabajo' => TipoTrabajoImpresion::COMANDA,
                'estado' => $printer ? EstadoImpresion::PENDIENTE : EstadoImpresion::ERROR,
                'contenido' => $contenido,
                'ultimo_error' => $printer ? null : 'No hay impresora de comanda configurada.',
            ],
        );

        $pendingLines->each(fn ($line): bool => $line->update(['tanda_id' => $job->getKey()]));

        return $job;
    }

    private function createTicketJob(Pedido $pedido, Pago $pago, User $actor): ?TrabajoImpresion
    {
        $printer = Impresora::buscar(TipoImpresora::TICKET);
        $contenido = $this->ticketRenderer->render($pedido, $pago, $actor);
        $uid = hash('sha256', $pedido->getKey().'|TICKET');

        return TrabajoImpresion::firstOrCreate(
            ['original_uid' => $uid],
            [
                'impresora_id' => $printer?->getKey(),
                'pedido_id' => $pedido->getKey(),
                'tipo_trabajo' => TipoTrabajoImpresion::TICKET,
                'estado' => $printer ? EstadoImpresion::PENDIENTE : EstadoImpresion::ERROR,
                'contenido' => $contenido,
                'ultimo_error' => $printer ? null : 'No hay impresora de ticket configurada.',
            ],
        );
    }

    private function registrarVentaFiscal(Pago $pago): void
    {
        $this->fiscalSaleRegistrar->register($pago);
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

    private function generateInternalPaymentReference(Pedido $pedido): string
    {
        $fecha = now()->format('ymd');
        $codigo = $pedido->codigo_corto ?? rand(1000, 9999);

        return "REF-{$fecha}-{$codigo}";
    }
}
