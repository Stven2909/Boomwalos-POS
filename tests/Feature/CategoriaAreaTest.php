<?php

namespace Tests\Feature;

use App\Enums\AreaPreparacion;
use App\Models\Categoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoriaAreaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_category_inherits_the_area_from_its_group(): void
    {
        $group = Categoria::create(['nombre' => 'Bebidas', 'area' => AreaPreparacion::BAR]);
        $child = Categoria::create(['nombre' => 'Cervezas', 'parent_id' => $group->getKey()]);

        $this->assertSame(AreaPreparacion::BAR, $child->areaResuelta());
    }

    public function test_a_category_area_overrides_the_group(): void
    {
        $group = Categoria::create(['nombre' => 'Bebidas', 'area' => AreaPreparacion::BAR]);
        $child = Categoria::create([
            'nombre' => 'Café caliente',
            'parent_id' => $group->getKey(),
            'area' => AreaPreparacion::COCINA,
        ]);

        $this->assertSame(AreaPreparacion::COCINA, $child->areaResuelta());
    }

    public function test_a_category_without_area_anywhere_resolves_to_null(): void
    {
        $categoria = Categoria::create(['nombre' => 'Varios']);

        $this->assertNull($categoria->areaResuelta());
    }
}
