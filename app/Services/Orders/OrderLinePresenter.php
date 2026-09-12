<?php

namespace App\Services\Orders;

use App\Models\DetallePedido;

final class OrderLinePresenter
{
    public function name(DetallePedido $line): string
    {
        return $line->combo?->nombre ?? $line->producto?->nombre ?? 'Producto';
    }

    public function mass(DetallePedido $line): ?string
    {
        $mass = data_get($line->configuracion_producto, 'masa.nombre');

        return filled($mass) ? (string) $mass : null;
    }

    public function comboSummary(DetallePedido $line): string
    {
        return collect($line->seleccion_combo ?? [])
            ->flatMap(fn (array $group): array => collect($group['items'] ?? [])
                ->map(function (array $item): string {
                    $mass = data_get($item, 'masa.nombre');

                    return (int) ($item['cantidad'] ?? 0).' '.($item['nombre'] ?? 'Producto')
                        .($mass ? ' · '.$mass : '');
                })
                ->all())
            ->filter()
            ->implode(', ');
    }
}
