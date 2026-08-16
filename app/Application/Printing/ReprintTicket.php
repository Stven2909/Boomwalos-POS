<?php

namespace App\Application\Printing;

use App\Contracts\EstablishmentContextInterface;
use App\Enums\EstadoImpresion;
use App\Enums\TipoImpresora;
use App\Enums\TipoTrabajoImpresion;
use App\Models\Impresora;
use App\Models\Pedido;
use App\Models\TrabajoImpresion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class ReprintTicket
{
    public function __construct(
        private readonly QueueCustomerTicket $customerTicket,
        private readonly EstablishmentContextInterface $establishmentContext,
    ) {}

    public function handle(Pedido $pedido, User $actor, string $motivo = 'Reimpresión manual'): QueueTicketResult
    {
        // Corrección de seguridad: un operador no puede reimprimir tickets de
        // una sucursal distinta a la activa en el contexto.
        if ($pedido->establecimiento_id !== $this->establishmentContext->id()) {
            throw new AuthorizationException('No puedes reimprimir tickets de otra sucursal.');
        }

        $printer = Impresora::query()
            ->where('tipo', TipoImpresora::TICKET->value)
            ->orderBy('id')
            ->first();

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

            $contenido = $this->customerTicket->renderContent($pedido, $pago, $actor);
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
        ]);

        return QueueTicketResult::created($trabajo);
    }
}
