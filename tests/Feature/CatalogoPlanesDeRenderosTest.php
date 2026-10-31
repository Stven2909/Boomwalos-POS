<?php

namespace Tests\Feature;

use App\Enums\AreaPreparacion;
use App\Models\Categoria;
use App\Models\Combo;
use App\Models\Establecimiento;
use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogoPlanesDeRenderosTest extends TestCase
{
    use RefreshDatabase;

    public function test_siembra_el_menu_completo_de_planes_de_renderos(): void
    {
        $branch = Establecimiento::create([
            'nombre' => 'Los Boomwalos Planes de Renderos',
            'direccion' => 'Carretera a Panchimalco',
        ]);

        $command = [
            'establecimiento' => (string) $branch->getKey(),
            '--data' => 'database/seeders/data/catalogo-planes-de-renderos.php',
        ];

        $this->artisan('pos:catalogo:sembrar', $command)->assertSuccessful();
        $this->artisan('pos:catalogo:sembrar', $command)->assertSuccessful();

        $this->assertSame(18, Categoria::query()
            ->where('establecimiento_id', $branch->getKey())
            ->whereNull('parent_id')
            ->count());

        $this->assertSame(23, Categoria::query()
            ->where('establecimiento_id', $branch->getKey())
            ->whereNotNull('parent_id')
            ->count());

        $this->assertSame(190, Producto::query()
            ->where('establecimiento_id', $branch->getKey())
            ->count());

        $this->assertSame(0, Combo::query()
            ->where('establecimiento_id', $branch->getKey())
            ->count());

        $this->assertSame(2.85, (float) Producto::query()
            ->where('establecimiento_id', $branch->getKey())
            ->where('nombre', 'Pupusa de Birria')
            ->value('precio'));

        $this->assertSame(6.00, (float) Producto::query()
            ->where('establecimiento_id', $branch->getKey())
            ->where('nombre', 'Limonada Boomwalos (Grande)')
            ->value('precio'));

        $this->assertSame(11.00, (float) Producto::query()
            ->where('establecimiento_id', $branch->getKey())
            ->where('nombre', 'Pilsener (Balde)')
            ->value('precio'));

        $pupusas = Categoria::query()
            ->where('establecimiento_id', $branch->getKey())
            ->where('nombre', 'Love Pupusas')
            ->firstOrFail();

        $tradicionales = Categoria::query()
            ->where('establecimiento_id', $branch->getKey())
            ->where('nombre', 'Tradicionales')
            ->firstOrFail();

        $bebidas = Categoria::query()
            ->where('establecimiento_id', $branch->getKey())
            ->where('nombre', 'Bebidas con Alcohol')
            ->firstOrFail();

        $this->assertSame(AreaPreparacion::PUPUSAS, $pupusas->area);
        $this->assertSame(AreaPreparacion::PUPUSAS, $tradicionales->areaResuelta());
        $this->assertSame(AreaPreparacion::BAR, $bebidas->area);

        $alitasTime = Categoria::query()
            ->where('establecimiento_id', $branch->getKey())
            ->where('nombre', 'Alitas Time')
            ->firstOrFail();

        $this->assertSame(
            $alitasTime->getKey(),
            Producto::query()
                ->where('establecimiento_id', $branch->getKey())
                ->where('nombre', 'Alitas (con salsa de la casa)')
                ->value('categoria_id'),
        );
    }

    public function test_reconcilia_productos_y_categorias_sobrantes(): void
    {
        $branch = Establecimiento::create([
            'nombre' => 'Los Boomwalos Planes de Renderos',
            'direccion' => 'Carretera a Panchimalco',
        ]);

        $data = 'database/seeders/data/catalogo-planes-de-renderos.php';

        $this->artisan('pos:catalogo:sembrar', [
            'establecimiento' => (string) $branch->getKey(),
            '--data' => $data,
        ])->assertSuccessful();

        $entradas = Categoria::query()
            ->where('establecimiento_id', $branch->getKey())
            ->where('nombre', 'Entradas')
            ->firstOrFail();

        // Sobrante con el mismo nombre que un producto del catálogo, pero en
        // otra categoría: debe fusionarse con el producto vigente.
        Producto::create([
            'establecimiento_id' => $branch->getKey(),
            'categoria_id' => $entradas->getKey(),
            'nombre' => 'Corona (Individual)',
            'precio' => 3,
        ]);

        // Sobrante que ya no existe en el catálogo: debe eliminarse.
        Producto::create([
            'establecimiento_id' => $branch->getKey(),
            'categoria_id' => $entradas->getKey(),
            'nombre' => 'Producto Viejo XYZ',
            'precio' => 1,
        ]);

        // Categoría vieja vacía: debe eliminarse.
        Categoria::create([
            'establecimiento_id' => $branch->getKey(),
            'nombre' => 'Categoría Vieja',
            'parent_id' => null,
            'activa' => true,
        ]);

        $this->assertSame(192, Producto::query()
            ->where('establecimiento_id', $branch->getKey())
            ->count());

        $this->artisan('pos:catalogo:reconciliar', [
            'establecimiento' => (string) $branch->getKey(),
            '--data' => $data,
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame(190, Producto::query()
            ->where('establecimiento_id', $branch->getKey())
            ->count());

        $this->assertFalse(Producto::query()
            ->where('establecimiento_id', $branch->getKey())
            ->where('nombre', 'Producto Viejo XYZ')
            ->exists());

        $this->assertFalse(Producto::query()
            ->where('categoria_id', $entradas->getKey())
            ->where('nombre', 'Corona (Individual)')
            ->exists());

        $this->assertFalse(Categoria::query()
            ->where('establecimiento_id', $branch->getKey())
            ->where('nombre', 'Categoría Vieja')
            ->exists());
    }
}
