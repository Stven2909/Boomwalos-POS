<?php

namespace App\Application\Printing;

use App\Contracts\BrandingServiceInterface;
use App\Enums\MetodoPago;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\User;

class RenderCustomerTicket
{
    public function __construct(
        private readonly BrandingServiceInterface $branding,
    ) {}

    public function render(Pedido $pedido, Pago $pago, User $actor): string
    {
        $pedido->loadMissing([
            'establecimiento',
            'mesa',
            'detalles.producto',
            'detalles.combo',
            'detalles.detallePedidoNotas.notaCocina',
        ]);

        $lineas = [];

        $lineas[] = $this->establecimiento($pedido);
        $lineas[] = 'TICKET DE CLIENTE';
        $lineas[] = $this->destino($pedido);
        $lineas[] = 'Pedido: ' . ($pedido->codigo_corto ?? $pedido->numero_seguimiento);
        $lineas[] = 'Fecha: ' . now()->setTimezone('America/El_Salvador')->format('d/m/Y H:i');
        $lineas[] = str_repeat('-', 32);

        foreach ($pedido->detalles as $detalle) {
            if ($detalle->estado_linea?->value !== 'ACTIVA') {
                continue;
            }

            $nombre = $detalle->combo?->nombre ?? $detalle->producto?->nombre ?? 'Producto';
            $precio = number_format((float) $detalle->precio_unitario, 2);
            $lineas[] = "{$detalle->cantidad} x {$nombre}  \${$precio}";

            foreach ($detalle->seleccion_combo ?? [] as $grupo) {
                foreach ($grupo['items'] ?? [] as $item) {
                    $lineas[] = "  - {$item['cantidad']} {$item['nombre']}";
                }
            }

            foreach ($detalle->detallePedidoNotas as $notaDetalle) {
                if ($notaDetalle->notaCocina?->nombre) {
                    $lineas[] = "  * {$notaDetalle->notaCocina->nombre}";
                }
            }
        }

        $lineas[] = str_repeat('-', 32);
        $lineas[] = 'TOTAL  $' . number_format($pedido->total(), 2);
        $lineas[] = 'PAGO   ' . $pago->metodo_pago->label();

        if ($pago->metodo_pago === MetodoPago::EFECTIVO) {
            $lineas[] = 'RECIBIDO $' . number_format((float) $pago->monto_recibido, 2);
            $lineas[] = 'CAMBIO  $' . number_format((float) $pago->cambio_devuelto, 2);
        }

        $lineas[] = '';
        $lineas[] = 'ATENDIDO POR: ' . $actor->nombre;

        $footer = $this->branding->ticketFooter();
        if ($footer) {
            $lineas[] = $footer;
        }

        $portalUrl = env('WEBFACT_URL', env('FRONTEND_URL', 'https://webfact.vercel.app'));
        $tracking = $pedido->numero_seguimiento ?: ($pedido->codigo_corto ? (string) $pedido->codigo_corto : (string) $pedido->getKey());
        $urlFactura = rtrim((string) $portalUrl, '/') . '/?tracking=' . urlencode($tracking);

        $lineas[] = str_repeat('-', 32);
        $lineas[] = '¿DESEA FACTURA O CCF?';
        $lineas[] = 'Escanea el QR o ingresa a:';
        $lineas[] = rtrim((string) $portalUrl, '/');
        $lineas[] = 'Tracking: ' . $tracking;
        $lineas[] = 'QR_URL: ' . $urlFactura;
        $lineas[] = str_repeat('-', 32);
        $lineas[] = '¡GRACIAS POR SU PREFERENCIA!';

        return implode("\n", $lineas) . "\n";
    }

    private function establecimiento(Pedido $pedido): string
    {
        return mb_strtoupper($pedido->establecimiento?->nombre ?: 'POS');
    }

    private function destino(Pedido $pedido): string
    {
        return $pedido->mesa
            ? 'MESA ' . $pedido->mesa->numero
            : 'PARA LLEVAR · MOSTRADOR';
    }
}
