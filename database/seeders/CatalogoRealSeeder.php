<?php

namespace Database\Seeders;

use App\Contracts\EstablishmentContextInterface;
use App\Enums\DisponibilidadProducto;
use App\Models\Categoria;
use App\Models\Combo;
use App\Models\Establecimiento;
use App\Models\Producto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CatalogoRealSeeder extends Seeder
{
    private const CARPETA_IMAGENES_PRODUCTOS = 'productos';

    private const CARPETA_IMAGENES_COMBOS = 'combos';

    public ?int $establecimientoId = null;

    public ?string $dataFile = null;

    private int $branchId = 0;

    public function run(): void
    {
        $this->branchId = $this->resolveEstablecimientoId();
        $catalogo = require $this->dataFile ?? __DIR__.'/data/catalogo.php';

        $this->sembrarGrupos($catalogo['grupos'] ?? []);
        $this->sembrarCombos($catalogo['combos'] ?? []);
    }

    private function resolveEstablecimientoId(): int
    {
        if ($this->establecimientoId !== null) {
            if (! Establecimiento::query()->whereKey($this->establecimientoId)->exists()) {
                throw new RuntimeException("La sucursal {$this->establecimientoId} no existe.");
            }

            return $this->establecimientoId;
        }

        $contextId = app(EstablishmentContextInterface::class)->idOrNull();

        if ($contextId !== null) {
            return $contextId;
        }

        $firstId = Establecimiento::query()->orderBy('id')->value('id');

        if ($firstId === null) {
            throw new RuntimeException('No hay sucursales. Crea una o indica el ID con --establecimiento.');
        }

        return (int) $firstId;
    }

    private function sembrarGrupos(array $grupos): void
    {
        foreach ($grupos as $grupo) {
            $grupoModel = Categoria::updateOrCreate(
                [
                    'establecimiento_id' => $this->branchId,
                    'nombre' => $grupo['nombre'],
                ],
                [
                    'descripcion' => $grupo['descripcion'] ?? null,
                    'parent_id' => null,
                    'activa' => true,
                    'icono' => $grupo['icono'] ?? null,
                    'area' => $grupo['area'] ?? null,
                ],
            );

            foreach ($grupo['categorias'] ?? [] as $categoria) {
                $categoriaModel = Categoria::updateOrCreate(
                    [
                        'establecimiento_id' => $this->branchId,
                        'nombre' => $categoria['nombre'],
                    ],
                    [
                        'descripcion' => $categoria['descripcion'] ?? null,
                        'parent_id' => $grupoModel->getKey(),
                        'activa' => true,
                        'area' => $categoria['area'] ?? null,
                    ],
                );

                foreach ($categoria['productos'] ?? [] as $producto) {
                    $this->crearProducto($categoriaModel, $producto);
                }
            }
        }
    }

    private function crearProducto(Categoria $categoria, array $datos): void
    {
        $imagenUrl = $this->copiarImagen($datos['imagen'] ?? null, self::CARPETA_IMAGENES_PRODUCTOS);

        $producto = Producto::updateOrCreate(
            [
                'establecimiento_id' => $this->branchId,
                'categoria_id' => $categoria->getKey(),
                'nombre' => $datos['nombre'],
            ],
            [
                'precio' => $datos['precio'],
                'disponibilidad' => DisponibilidadProducto::DISPONIBLE,
                'requiere_masa' => (bool) ($datos['requiere_masa'] ?? false),
            ],
        );

        if ($imagenUrl !== null) {
            $producto->update(['imagen_url' => $imagenUrl]);
        }
    }

    private function sembrarCombos(array $combos): void
    {
        foreach ($combos as $datos) {
            $imagenUrl = $this->copiarImagen($datos['imagen'] ?? null, self::CARPETA_IMAGENES_COMBOS);

            $combo = Combo::updateOrCreate(
                [
                    'establecimiento_id' => $this->branchId,
                    'nombre' => $datos['nombre'],
                ],
                [
                    'precio_fijo' => $datos['precio_fijo'],
                    'disponibilidad' => DisponibilidadProducto::DISPONIBLE,
                ],
            );

            if ($imagenUrl !== null) {
                $combo->update(['imagen_url' => $imagenUrl]);
            }

            foreach ($datos['opciones'] ?? [] as $opcion) {
                $nombres = $opcion['productos'] ?? [];
                $productos = Producto::query()
                    ->where('establecimiento_id', $this->branchId)
                    ->whereIn('nombre', $nombres)
                    ->get();

                $faltantes = collect($nombres)->diff($productos->pluck('nombre'));

                if ($faltantes->isNotEmpty()) {
                    throw new RuntimeException(
                        "El combo {$datos['nombre']} referencia productos inexistentes en la sucursal: "
                        .$faltantes->implode(', '),
                    );
                }

                $opcionModel = $combo->opcionesCombo()->updateOrCreate(
                    ['nombre' => $opcion['nombre']],
                    [
                        'cantidad_requerida' => (int) ($opcion['cantidad_requerida'] ?? 1),
                        'es_obligatorio' => (bool) ($opcion['es_obligatorio'] ?? true),
                    ],
                );

                $opcionModel->productos()->sync($productos->pluck('id')->all());
            }
        }
    }

    private function copiarImagen(?string $origenRelativa, string $carpetaDestino): ?string
    {
        $directorio = (string) config('pos.catalogo_images_dir');

        if (! is_dir($directorio)) {
            $directorio = database_path('seeders/assets/catalogo');
        }

        if ($origenRelativa === null) {
            return null;
        }

        $rutaOrigen = $directorio.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $origenRelativa);

        if (! is_file($rutaOrigen)) {
            return null;
        }

        $nombre = strtolower(basename($rutaOrigen));
        $destino = $carpetaDestino.'/'.$nombre;
        $disco = Storage::disk('public');

        if (! $disco->exists($destino) || $disco->size($destino) !== filesize($rutaOrigen)) {
            $disco->put($destino, file_get_contents($rutaOrigen));
        }

        return $destino;
    }
}
