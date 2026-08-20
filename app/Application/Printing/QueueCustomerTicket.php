<?php

namespace App\Application\Printing;

use App\Contracts\CustomerTicketDispatcherInterface;
use App\Contracts\BrandingServiceInterface;
use App\Enums\EstadoImpresion;
use App\Enums\MetodoPago;
use App\Enums\TipoImpresora;
use App\Enums\TipoTrabajoImpresion;
use App\Models\Impresora;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\TrabajoImpresion;
use App\Models\User;

class QueueCustomerTicket implements CustomerTicketDispatcherInterface
{
    public function __construct(private readonly BrandingServiceInterface $branding) {}

    public function dispatch(Pedido $pedido, Pago $pago, User $actor): QueueTicketResult
    {
        return $this->handle($pedido, $pago, $actor);
    }

    public function handle(Pedido $pedido, Pago $pago, User $actor): QueueTicketResult
    {
        $printer = Impresora::query()
            ->where('tipo', TipoImpresora::TICKET->value)
            ->orderBy('id')
            ->first();

        if (! $printer) {
            return QueueTicketResult::noPrinter();
        }

        $pedido->loadMissing([
            'mesa',
            'detalles.producto',
            'detalles.combo',
            'detalles.detallePedidoNotas.notaCocina',
        ]);

        $originalUid = $this->originalUid($printer->getKey(), $pedido->getKey());

        $trabajo = TrabajoImpresion::firstOrCreate(
            ['original_uid' => $originalUid],
            [
                'impresora_id' => $printer->getKey(),
                'pedido_id' => $pedido->getKey(),
                'tipo_trabajo' => TipoTrabajoImpresion::TICKET,
                'estado' => EstadoImpresion::PENDIENTE,
                'contenido' => $this->renderContent($pedido, $pago, $actor),
            ],
        );

        return QueueTicketResult::created($trabajo);
    }

    public function renderContent(Pedido $pedido, Pago $pago, User $actor): string
    {
        $destination = $pedido->mesa
            ? 'MESA ' . $pedido->mesa->numero
            : 'PARA LLEVAR · MOSTRADOR';

        $lines = [
            $this->encabezado($pedido),
            'TICKET DE CLIENTE',
            $destination,
            'PEDIDO ' . $pedido->numero_seguimiento,
            'FECHA ' . $this->fechaHora(),
            str_repeat('-', 32),
        ];

        foreach ($pedido->detalles as $detail) {
            if ($detail->estado_linea->value !== 'ACTIVA') {
                continue;
            }

            $name = $detail->combo?->nombre ?? $detail->producto?->nombre ?? 'Producto';
            $lines[] = $detail->cantidad . ' x ' . $name;

            foreach ($detail->seleccion_combo ?? [] as $group) {
                foreach ($group['items'] ?? [] as $item) {
                    $lines[] = '  - ' . $item['cantidad'] . ' ' . $item['nombre'];
                }
            }

            foreach ($detail->detallePedidoNotas as $note) {
                $lines[] = '  * ' . $note->notaCocina?->nombre;
            }
        }

        $lines[] = str_repeat('-', 32);
        $lines[] = 'TOTAL  $' . number_format($pedido->total(), 2);
        $lines[] = 'PAGO   ' . $pago->metodo_pago->label();

        if ($pago->metodo_pago === MetodoPago::EFECTIVO) {
            $lines[] = 'RECIBIDO $' . number_format((float) $pago->monto_recibido, 2);
            $lines[] = 'CAMBIO  $' . number_format((float) $pago->cambio_devuelto, 2);
        }

        $lines[] = '';
        $lines[] = 'ATENDIDO POR: ' . $actor->nombre;

        if ($this->branding->ticketFooter()) {
            $lines[] = $this->branding->ticketFooter();
        }

        $qrLine = $this->qrLine($pedido);

        if ($qrLine !== null) {
            $lines[] = $qrLine;
        } else {
            $lines[] = 'DOCUMENTO FISCAL PENDIENTE DE SINCRONIZACIÓN.';
            $lines[] = 'CONSERVE ESTE TICKET.';
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    protected function encabezado(Pedido $pedido): string
    {
        return mb_strtoupper((string) ($pedido->establecimiento?->nombre ?: 'POS'));
    }

    protected function fechaHora(): string
    {
        return now()->setTimezone('America/El_Salvador')->format('d/m/Y H:i');
    }

    protected function qrLine(Pedido $pedido): ?string
    {
        $portalUrl = env('WEBFACT_URL', env('FRONTEND_URL', 'https://webfact.vercel.app'));
        $tracking = $pedido->numero_seguimiento;
        $urlFactura = rtrim((string) $portalUrl, '/') . '/?tracking=' . urlencode($tracking);

        return implode(PHP_EOL, [
            str_repeat('-', 32),
            '¿SOLICITAR FACTURA O CCF?',
            'Escanea el QR o ingresa a:',
            rtrim((string) $portalUrl, '/'),
            'Tracking: ' . $tracking,
            'QR_URL: ' . $urlFactura,
            str_repeat('-', 32),
            '¡GRACIAS POR SU PREFERENCIA!',
        ]);
    }

    protected function originalUid(int $printerId, int $pedidoId): string
    {
        return hash('sha256', $printerId . '|' . $pedidoId . '|' . TipoTrabajoImpresion::TICKET->value);
    }
}
