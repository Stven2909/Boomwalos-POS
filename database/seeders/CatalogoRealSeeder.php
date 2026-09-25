<?php

namespace Database\Seeders;

use App\Enums\DisponibilidadProducto;
use App\Models\Categoria;
use App\Models\Combo;
use App\Models\Producto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class CatalogoRealSeeder extends Seeder
{
    private const CARPETA_IMAGENES_PRODUCTOS = 'productos';

    private const CARPETA_IMAGENES_COMBOS = 'combos';

    public function run(): void
    {
        $catalogo = require __DIR__.'/data/catalogo.php';

        $this->sembrarGrupos($catalogo['grupos'] ?? []);
        $this->sembrarCombos($catalogo['combos'] ?? []);
    }

    private function sembrarGrupos(array $grupos): void
    {
        foreach ($grupos as $grupo) {
            $grupoModel = Categoria::updateOrCreate(
                ['nombre' => $grupo['nombre']],
                [
                    'descripcion' => $grupo['descripcion'] ?? null,
                    'parent_id' => null,
                    'activa' => true,
                    'icono' => $grupo['icono'] ?? null,
                ],
            );

            foreach ($grupo['categorias'] ?? [] as $categoria) {
                $categoriaModel = Categoria::updateOrCreate(
                    ['nombre' => $categoria['nombre']],
                    [
                        'descripcion' => $categoria['descripcion'] ?? null,
                        'parent_id' => $grupoModel->getKey(),
                        'activa' => true,
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
            ['nombre' => $datos['nombre'], 'categoria_id' => $categoria->getKey()],
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
                ['nombre' => $datos['nombre']],
                [
                    'precio_fijo' => $datos['precio_fijo'],
                    'disponibilidad' => DisponibilidadProducto::DISPONIBLE,
                ],
            );

            if ($imagenUrl !== null) {
                $combo->update(['imagen_url' => $imagenUrl]);
            }

            foreach ($datos['opciones'] ?? [] as $opcion) {
                $productos = Producto::whereIn('nombre', $opcion['productos'] ?? [])->get();

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

        if ($directorio === '' || $origenRelativa === null) {
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
