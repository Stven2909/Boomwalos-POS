<?php

namespace App\Application\Printing;

use App\Contracts\EstablishmentContextInterface;
use App\Enums\EstadoImpresion;
use App\Enums\TipoImpresora;
use App\Enums\TipoTrabajoImpresion;
use App\Jobs\ProcessPrintJob;
use App\Models\Impresora;
use App\Models\Pedido;
use App\Models\TrabajoImpresion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class ReprintTicket
{
    public function __construct(
        private readonly RenderCustomerTicket $ticketRenderer,
        private readonly EstablishmentContextInterface $establishmentContext,
    ) {}

    public function handle(Pedido $pedido, User $actor, string $motivo = 'Reimpresión manual'): QueueTicketResult
    {
        if ($pedido->establecimiento_id !== $this->establishmentContext->id()) {
            throw new AuthorizationException('No puedes reimprimir tickets de otra sucursal.');
        }

        $printer = Impresora::buscar(TipoImpresora::TICKET);

        if (! $printer) {
            return QueueTicketResult::noPrinter();
        }

        $original = TrabajoImpresion::query()
            ->where('pedido_id', $pedido->getKey())
            ->where('tipo_trabajo', TipoTrabajoImpresion::TICKET->value)
            ->where('es_reimpresion', false)
            ->latest('id')
            ->first();

        $contenido = $original?->contenido;

        if ($contenido === null) {
            $pago = $pedido->pago()->first();

            if (! $pago) {
                return QueueTicketResult::failed('No existe ticket original ni pago registrado para este pedido.');
            }

            $contenido = $this->ticketRenderer->render($pedido, $pago, $actor);
        }

        $trabajo = TrabajoImpresion::create([
            'impresora_id' => $printer->getKey(),
            'pedido_id' => $pedido->getKey(),
            'tipo_trabajo' => TipoTrabajoImpresion::TICKET,
            'es_reimpresion' => true,
            'reimpresion_de_id' => $original?->getKey(),
            'motivo_reimpresion' => $motivo,
            'usuario_reimpresion_id' => $actor->getKey(),
            'estado' => EstadoImpresion::PENDIENTE,
            'contenido' => $contenido,
            'original_uid' => hash('sha256', $pedido->getKey() . '|TICKET_REIMPRESION|' . now()->timestamp),
        ]);

        ProcessPrintJob::dispatch($trabajo->getKey())->afterCommit();

        return QueueTicketResult::created($trabajo);
    }
}
