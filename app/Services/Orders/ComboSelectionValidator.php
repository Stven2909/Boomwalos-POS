<?php

namespace App\Services\Orders;

use App\Enums\DisponibilidadProducto;
use App\Enums\MasaPupusa;
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

            foreach ($rawItems as $productId => $rawQuantity) {
                $product = $allowedProducts->get((string) $productId);

                if (! $product) {
                    throw ValidationException::withMessages([
                        'combo' => 'La selección contiene un producto que no pertenece al combo.',
                    ]);
                }

                $hasMassBuckets = is_array($rawQuantity);
                $massBuckets = $hasMassBuckets ? $rawQuantity : ['' => $rawQuantity];

                foreach ($massBuckets as $rawMass => $quantity) {
                    $quantity = (int) $quantity;

                    if ($quantity < 1) {
                        continue;
                    }

                    $massCode = strtoupper(trim((string) $rawMass));
                    $mass = null;

                    if ($product->requiere_masa) {
                        if ($massCode === '') {
                            if ($hasMassBuckets) {
                                throw ValidationException::withMessages([
                                    'combo' => "Selecciona maíz o arroz para {$product->nombre}.",
                                ]);
                            }
                        } else {
                            $mass = MasaPupusa::tryFrom($massCode);

                            if (! $mass) {
                                throw ValidationException::withMessages([
                                    'combo' => 'La selección contiene una masa inválida.',
                                ]);
                            }
                        }
                    } elseif ($massCode !== '') {
                        throw ValidationException::withMessages([
                            'combo' => "{$product->nombre} no permite selección de masa.",
                        ]);
                    }

                    $item = [
                        'producto_id' => $product->getKey(),
                        'nombre' => $product->nombre,
                        'cantidad' => $quantity,
                    ];

                    if ($mass) {
                        $item['masa'] = [
                            'codigo' => $mass->value,
                            'nombre' => $mass->label(),
                        ];
                    }

                    $items[] = $item;
                    $total += $quantity;
                }
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
