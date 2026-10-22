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

        $this->assertSame(179, Producto::query()
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
    }
}
