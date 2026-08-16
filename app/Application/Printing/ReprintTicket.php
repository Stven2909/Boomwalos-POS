<?php

namespace App\Application\Printing;

use App\Enums\EstadoImpresion;
use App\Enums\TipoImpresora;
use App\Enums\TipoTrabajoImpresion;
use App\Models\Impresora;
use App\Models\Pedido;
use App\Models\TrabajoImpresion;
use App\Models\User;

class ReprintTicket
{
    public function __construct(private readonly QueueCustomerTicket $customerTicket) {}

    public function handle(Pedido $pedido, User $actor, string $motivo = 'Reimpresión manual'): QueueTicketResult
    {
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
