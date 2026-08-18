<?php

namespace App\Application\Printing;

use App\Contracts\BrandingServiceInterface;
use App\Models\Pedido;

class RenderKitchenComanda
{
    public function __construct(
        private readonly BrandingServiceInterface $branding,
    ) {}

    public function render(Pedido $pedido): string
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
        $lineas[] = 'COMANDA';
        $lineas[] = $this->destino($pedido);
        $lineas[] = 'Pedido: ' . $pedido->codigo_corto;
        $lineas[] = 'Fecha: ' . now()->setTimezone('America/El_Salvador')->format('d/m/Y H:i');
        $lineas[] = str_repeat('-', 32);

        foreach ($pedido->detalles as $detalle) {
            if ($detalle->estado_linea?->value !== 'ACTIVA') {
                continue;
            }

            $nombre = $detalle->combo?->nombre ?? $detalle->producto?->nombre ?? 'Producto';
            $lineas[] = "{$detalle->cantidad} x {$nombre}";

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

        $footer = $this->branding->ticketFooter();
        if ($footer) {
            $lineas[] = $footer;
        }

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
