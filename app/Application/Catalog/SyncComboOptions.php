<?php

namespace App\Application\Catalog;

use App\Models\Combo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SyncComboOptions
{
    public function handle(Combo $combo, array $options): void
    {
        DB::transaction(function () use ($combo, $options): void {
            $combo->opcionesCombo()->delete();

            foreach (array_values($options) as $option) {
                $name = trim((string) ($option['nombre'] ?? ''));
                $required = (int) ($option['cantidad_requerida'] ?? 0);
                $productIds = collect($option['producto_ids'] ?? [])
                    ->map(fn ($id): int => (int) $id)
                    ->filter()
                    ->unique()
                    ->values();

                if ($name === '' || $required < 1 || $productIds->isEmpty()) {
                    throw ValidationException::withMessages([
                        'opciones' => 'Cada grupo del combo necesita nombre, cantidad y al menos un producto.',
                    ]);
                }

                $optionModel = $combo->opcionesCombo()->create([
                    'nombre' => $name,
                    'cantidad_requerida' => $required,
                    'es_obligatorio' => (bool) ($option['es_obligatorio'] ?? true),
                ]);

                $optionModel->productos()->sync($productIds->all());
            }
        });
    }

    public function formState(Combo $combo): array
    {
        return $combo->load('opcionesCombo.productos')->opcionesCombo
            ->map(fn ($option): array => [
                'nombre' => $option->nombre,
                'cantidad_requerida' => $option->cantidad_requerida,
                'es_obligatorio' => $option->es_obligatorio,
                'producto_ids' => $option->productos->modelKeys(),
            ])
            ->all();
    }
}
