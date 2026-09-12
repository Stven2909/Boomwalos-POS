<?php

namespace App\Application\Printing;

use App\Contracts\BrandingServiceInterface;
use App\Models\Pedido;

class RenderKitchenComanda
{
    public function __construct(
        private readonly BrandingServiceInterface $branding,
    ) {}

    public function render(Pedido $pedido, ?iterable $detalles = null): string
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
        $lineas[] = 'Pedido: '.$pedido->codigo_corto;
        $lineas[] = 'Fecha: '.now()->setTimezone('America/El_Salvador')->format('d/m/Y H:i');
        $lineas[] = str_repeat('-', 32);

        $selectedDetails = $detalles === null ? $pedido->detalles : collect($detalles);

        foreach ($selectedDetails as $detalle) {
            if ($detalle->estado_linea?->value !== 'ACTIVA') {
                continue;
            }

            $detalle->loadMissing(['producto', 'combo', 'detallePedidoNotas.notaCocina']);

            $nombre = $detalle->combo?->nombre ?? $detalle->producto?->nombre ?? 'Producto';
            $lineas[] = "{$detalle->cantidad} x {$nombre}";
            $masaLinea = data_get($detalle->configuracion_producto, 'masa.nombre');

            if (! $detalle->combo_id && $masaLinea) {
                $lineas[] = '  Masa: '.$masaLinea;
            }

            foreach ($detalle->seleccion_combo ?? [] as $grupo) {
                foreach ($grupo['items'] ?? [] as $item) {
                    $cantidadItem = (int) $detalle->cantidad * (int) ($item['cantidad'] ?? 0);
                    $masaItem = data_get($item, 'masa.nombre') ?: $masaLinea;
                    $sufijoMasa = $masaItem ? ' · '.$masaItem : '';
                    $lineas[] = "  - {$cantidadItem} {$item['nombre']}{$sufijoMasa}";
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

        return implode("\n", $lineas)."\n";
    }

    private function establecimiento(Pedido $pedido): string
    {
        return mb_strtoupper($pedido->establecimiento?->nombre ?: 'POS');
    }

    private function destino(Pedido $pedido): string
    {
        return $pedido->mesa
            ? 'MESA '.$pedido->mesa->numero
            : 'PARA LLEVAR · MOSTRADOR';
    }
}
