<?php

namespace Tests\Feature;

use App\Models\Categoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoriaColorAcentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_pupusas_use_the_terracotta_accent(): void
    {
        $categoria = Categoria::create(['nombre' => 'Pupusas Normales']);

        $this->assertSame('#c2410c', $categoria->colorAcento());
    }

    public function test_hot_drinks_use_the_brown_accent(): void
    {
        $categoria = Categoria::create(['nombre' => 'Bebidas Calientes']);

        $this->assertSame('#92400e', $categoria->colorAcento());
    }

    public function test_cold_drinks_and_sodas_use_the_teal_accent(): void
    {
        $this->assertSame('#0e7490', Categoria::create(['nombre' => 'Bebidas Frias'])->colorAcento());
        $this->assertSame('#0e7490', Categoria::create(['nombre' => 'Sodas'])->colorAcento());
    }

    public function test_other_categories_fall_back_to_the_brand_color(): void
    {
        $categoria = Categoria::create(['nombre' => 'Postres']);

        $this->assertSame('#75556d', $categoria->colorAcento());
    }
}
