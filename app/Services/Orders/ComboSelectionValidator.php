<?php

namespace App\Services\Orders;

use App\Enums\DisponibilidadProducto;
use App\Models\Combo;
use App\Models\Producto;
use Illuminate\Validation\ValidationException;

class ComboSelectionValidator
{
    public function normalize(Combo $combo, array $selection): array
    {
        $normalized = [];

        foreach ($combo->opcionesCombo->sortBy('id') as $option) {
            $rawItems = $selection[(string) $option->getKey()] ?? $selection[$option->getKey()] ?? [];
            $rawItems = is_array($rawItems) ? $rawItems : [];
            $allowedProducts = $option->productos->keyBy(fn (Producto $product): string => (string) $product->getKey());
            $items = [];
            $total = 0;

            foreach ($rawItems as $productId => $quantity) {
                $product = $allowedProducts->get((string) $productId);
                $quantity = (int) $quantity;

                if ($quantity < 1) {
                    continue;
                }

                if (! $product) {
                    throw ValidationException::withMessages([
                        'combo' => 'La selección contiene un producto que no pertenece al combo.',
                    ]);
                }

                if ($product->disponibilidad !== DisponibilidadProducto::DISPONIBLE) {
                    throw ValidationException::withMessages([
                        'combo' => "{$product->nombre} no está disponible para este combo.",
                    ]);
                }

                $items[] = [
                    'producto_id' => $product->getKey(),
                    'nombre' => $product->nombre,
                    'cantidad' => $quantity,
                ];
                $total += $quantity;
            }

            if ($option->es_obligatorio && $total !== (int) $option->cantidad_requerida) {
                throw ValidationException::withMessages([
                    'combo' => "El grupo {$option->nombre} debe tener exactamente {$option->cantidad_requerida} unidades.",
                ]);
            }

            if (! $option->es_obligatorio && $total > 0 && $total !== (int) $option->cantidad_requerida) {
                throw ValidationException::withMessages([
                    'combo' => "El grupo {$option->nombre} debe tener exactamente {$option->cantidad_requerida} unidades.",
                ]);
            }

            $normalized[] = [
                'opcion_combo_id' => $option->getKey(),
                'nombre' => $option->nombre,
                'cantidad_requerida' => (int) $option->cantidad_requerida,
                'items' => $items,
            ];
        }

        return $normalized;
    }

    public function same(?array $left, array $right): bool
    {
        return json_encode($left ?? [], JSON_UNESCAPED_UNICODE) === json_encode($right, JSON_UNESCAPED_UNICODE);
    }
}
