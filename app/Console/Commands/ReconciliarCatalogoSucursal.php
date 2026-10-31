<?php

namespace App\Console\Commands;

use App\Enums\DisponibilidadProducto;
use App\Models\Categoria;
use App\Models\Establecimiento;
use App\Models\Producto;
use Database\Seeders\CatalogoRealSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconciliarCatalogoSucursal extends Command
{
    protected $signature = 'pos:catalogo:reconciliar
        {establecimiento : ID o nombre exacto de la sucursal}
        {--data= : Ruta del archivo PHP con el catálogo (por defecto database/seeders/data/catalogo.php)}
        {--apply : Ejecuta los cambios; sin esta opción solo muestra el reporte}';

    protected $description = 'Deja el catálogo de una sucursal exactamente como el archivo de datos, sin productos sobrantes de versiones anteriores.';

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

        $branchId = $establecimiento->getKey();
        $idsEsperados = array_values(array_unique($seeder->productosSembrados));
        $idsCategorias = array_values(array_unique($seeder->categoriasSembradas));

        $sobrantes = Producto::query()
            ->where('establecimiento_id', $branchId)
            ->whereNotIn('id', $idsEsperados)
            ->orderBy('nombre')
            ->get();

        $destinos = Producto::query()
            ->whereIn('id', $idsEsperados)
            ->get()
            ->keyBy(fn (Producto $producto): string => $this->clave($producto->nombre));

        $plan = [];

        foreach ($sobrantes as $sobrante) {
            $destino = $destinos->get($this->clave($sobrante->nombre));

            if ($destino) {
                $plan[] = ['accion' => 'fusionar', 'producto' => $sobrante, 'destino' => $destino];

                continue;
            }

            $referencias = $this->referencias($sobrante->getKey());

            $plan[] = [
                'accion' => $referencias > 0 ? 'no_disponible' : 'eliminar',
                'producto' => $sobrante,
                'destino' => null,
            ];
        }

        $categoriasSobrantes = Categoria::query()
            ->where('establecimiento_id', $branchId)
            ->whereNotIn('id', $idsCategorias)
            ->orderByRaw('parent_id IS NOT NULL')
            ->orderBy('nombre')
            ->get();

        $this->mostrarResumen($establecimiento, $idsEsperados, $plan, $categoriasSobrantes);

        if (! $this->option('apply')) {
            $this->newLine();
            $this->warn('Reporte solamente. Vuelve a ejecutar con --apply para aplicar los cambios.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($plan, $categoriasSobrantes): void {
            foreach ($plan as $fila) {
                /** @var Producto $producto */
                $producto = $fila['producto'];
                /** @var Producto|null $destino */
                $destino = $fila['destino'];

                if ($destino) {
                    $this->fusionar($producto, $destino);

                    continue;
                }

                if ($fila['accion'] === 'eliminar') {
                    $producto->delete();

                    continue;
                }

                $producto->update([
                    'disponibilidad' => DisponibilidadProducto::TEMPORALMENTE_NO_DISPONIBLE,
                ]);
            }

            foreach ($categoriasSobrantes as $categoria) {
                $tieneProductos = Producto::query()->where('categoria_id', $categoria->getKey())->exists();
                $tieneHijos = Categoria::query()->where('parent_id', $categoria->getKey())->exists();

                if (! $tieneProductos && ! $tieneHijos) {
                    $categoria->delete();

                    continue;
                }

                $categoria->update(['activa' => false]);
            }
        });

        $this->newLine();
        $this->info('Catálogo reconciliado. Productos activos en la sucursal: '.Producto::query()->where('establecimiento_id', $branchId)->count().'.');

        return self::SUCCESS;
    }

    /**
     * Mueve el historial del producto sobrante al producto que sí está en el
     * catálogo actual y después elimina el sobrante.
     */
    private function fusionar(Producto $sobrante, Producto $destino): void
    {
        DB::table('detalles_pedido')
            ->where('producto_id', $sobrante->getKey())
            ->update(['producto_id' => $destino->getKey()]);

        $pivotes = DB::table('opciones_combo_productos')
            ->where('producto_id', $sobrante->getKey())
            ->get();

        foreach ($pivotes as $pivote) {
            $yaExiste = DB::table('opciones_combo_productos')
                ->where('opcion_combo_id', $pivote->opcion_combo_id)
                ->where('producto_id', $destino->getKey())
                ->exists();

            if ($yaExiste) {
                DB::table('opciones_combo_productos')->where('id', $pivote->id)->delete();

                continue;
            }

            DB::table('opciones_combo_productos')
                ->where('id', $pivote->id)
                ->update(['producto_id' => $destino->getKey()]);
        }

        $sobrante->delete();
    }

    private function referencias(int $productoId): int
    {
        return DB::table('detalles_pedido')->where('producto_id', $productoId)->count()
            + DB::table('opciones_combo_productos')->where('producto_id', $productoId)->count();
    }

    private function mostrarResumen(
        Establecimiento $establecimiento,
        array $idsEsperados,
        array $plan,
        $categoriasSobrantes,
    ): void {
        $fusionar = collect($plan)->where('accion', 'fusionar')->count();
        $eliminar = collect($plan)->where('accion', 'eliminar')->count();
        $noDisponible = collect($plan)->where('accion', 'no_disponible')->count();

        $this->table(['Sucursal', 'Productos del catálogo', 'Sobrantes', 'Fusionar', 'Eliminar', 'No disponible'], [
            [
                $establecimiento->nombre.' (#'.$establecimiento->getKey().')',
                count($idsEsperados),
                count($plan),
                $fusionar,
                $eliminar,
                $noDisponible,
            ],
        ]);

        if ($plan !== []) {
            $this->newLine();
            $this->line('Productos sobrantes:');

            $this->table(
                ['Acción', 'Producto', 'Categoría actual', 'Destino'],
                collect($plan)
                    ->take(60)
                    ->map(fn (array $fila): array => [
                        $fila['accion'],
                        $fila['producto']->nombre,
                        $fila['producto']->categoria?->nombre ?? '—',
                        $fila['destino']?->categoria?->nombre ?? '—',
                    ])
                    ->all(),
            );

            if (count($plan) > 60) {
                $this->line('… y '.(count($plan) - 60).' producto(s) más.');
            }
        }

        if ($categoriasSobrantes->isNotEmpty()) {
            $this->newLine();
            $this->line('Categorías sobrantes:');

            $this->table(
                ['Categoría', 'Productos', 'Subcategorías'],
                $categoriasSobrantes->map(fn (Categoria $categoria): array => [
                    $categoria->nombre,
                    Producto::query()->where('categoria_id', $categoria->getKey())->count(),
                    Categoria::query()->where('parent_id', $categoria->getKey())->count(),
                ])->all(),
            );
        }
    }

    private function clave(string $nombre): string
    {
        return mb_strtolower(trim($nombre));
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
