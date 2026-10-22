<?php

namespace App\Console\Commands;

use App\Models\Categoria;
use App\Models\Combo;
use App\Models\Establecimiento;
use App\Models\Producto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AsignarCatalogoASucursal extends Command
{
    protected $signature = 'pos:catalogo:asignar
        {establecimiento : ID o nombre exacto de la sucursal}
        {--dry-run : Muestra cuántos registros se actualizarían sin guardar}';

    protected $description = 'Asigna el catálogo heredado (sin sucursal) a una sucursal.';

    public function handle(): int
    {
        $establecimiento = $this->resolveEstablecimiento((string) $this->argument('establecimiento'));

        if (! $establecimiento) {
            $this->error('No se encontró la sucursal indicada.');

            return self::FAILURE;
        }

        $categorias = Categoria::query()->whereNull('establecimiento_id')->count();
        $productos = Producto::query()->whereNull('establecimiento_id')->count();
        $combos = Combo::query()->whereNull('establecimiento_id')->count();

        if ($this->option('dry-run')) {
            $this->table(['Tabla', 'Pendientes'], [
                ['categorías', $categorias],
                ['productos', $productos],
                ['combos', $combos],
            ]);

            return self::SUCCESS;
        }

        DB::transaction(function () use ($establecimiento): void {
            Categoria::query()
                ->whereNull('establecimiento_id')
                ->update(['establecimiento_id' => $establecimiento->getKey()]);

            Producto::query()
                ->whereNull('establecimiento_id')
                ->with('categoria')
                ->get()
                ->each(function (Producto $producto) use ($establecimiento): void {
                    $producto->establecimiento_id = $producto->categoria?->establecimiento_id
                        ?? $establecimiento->getKey();
                    $producto->save();
                });

            Combo::query()
                ->whereNull('establecimiento_id')
                ->update(['establecimiento_id' => $establecimiento->getKey()]);
        });

        $this->table(['Tabla', 'Actualizados'], [
            ['categorías', $categorias],
            ['productos', $productos],
            ['combos', $combos],
        ]);

        $this->info("Catálogo asignado a {$establecimiento->nombre} (#{$establecimiento->getKey()}).");

        return self::SUCCESS;
    }

    private function resolveEstablecimiento(string $value): ?Establecimiento
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return Establecimiento::query()
            ->when(ctype_digit($value), fn ($query) => $query->whereKey((int) $value))
            ->when(! ctype_digit($value), fn ($query) => $query->where('nombre', $value))
            ->first();
    }
}
