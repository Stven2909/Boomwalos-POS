<?php

namespace App\Console\Commands;

use App\Models\Categoria;
use App\Models\Establecimiento;
use App\Models\Producto;
use Database\Seeders\CatalogoRealSeeder;
use Illuminate\Console\Command;

class SembrarCatalogoSucursal extends Command
{
    protected $signature = 'pos:catalogo:sembrar
        {establecimiento : ID o nombre exacto de la sucursal}
        {--data= : Ruta del archivo PHP con el catálogo (por defecto database/seeders/data/catalogo.php)}';

    protected $description = 'Siembra o actualiza el catálogo de una sucursal.';

    public function handle(): int
    {
        $establecimiento = $this->resolveEstablecimiento((string) $this->argument('establecimiento'));

        if (! $establecimiento) {
            $this->error('No se encontró la sucursal indicada.');

            return self::FAILURE;
        }

        $seeder = new CatalogoRealSeeder();
        $seeder->establecimientoId = $establecimiento->getKey();

        $data = $this->option('data');

        if (is_string($data) && $data !== '') {
            $path = is_file($data) ? $data : base_path($data);

            if (! is_file($path)) {
                $this->error("No se encontró el archivo de catálogo: {$path}");

                return self::FAILURE;
            }

            $seeder->dataFile = $path;
        }

        $seeder->setContainer($this->laravel);
        $seeder->setCommand($this);
        $seeder->run();

        $categorias = Categoria::query()
            ->where('establecimiento_id', $establecimiento->getKey())
            ->count();

        $productos = Producto::query()
            ->where('establecimiento_id', $establecimiento->getKey())
            ->count();

        $this->table(['Sucursal', 'Categorías', 'Productos'], [
            [$establecimiento->nombre.' (#'.$establecimiento->getKey().')', $categorias, $productos],
        ]);

        if ($categorias === 0 || $productos === 0) {
            $this->error('El seeder terminó pero no dejó categorías/productos en esa sucursal. Revisa la conexión de base de datos y que la migración de establecimiento_id esté aplicada.');

            return self::FAILURE;
        }

        $this->info("Catálogo sembrado para {$establecimiento->nombre} (#{$establecimiento->getKey()}).");

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
