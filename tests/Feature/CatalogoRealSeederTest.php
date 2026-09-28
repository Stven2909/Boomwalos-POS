<?php

namespace Tests\Feature;

use App\Models\Combo;
use App\Models\OpcionCombo;
use App\Models\OpcionComboProducto;
use App\Models\Producto;
use Database\Seeders\CatalogoRealSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogoRealSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['pos.catalogo_images_dir' => '']);
    }

    public function test_seed_catalogo_real_crea_el_catalogo_completo(): void
    {
        $this->seed(CatalogoRealSeeder::class);

        $this->assertSame(19, Producto::count());
        $this->assertSame(4, Combo::count());
        $this->assertSame(8, OpcionCombo::count());
        $this->assertSame(30, OpcionComboProducto::count());
    }

    public function test_seed_es_idempotente(): void
    {
        $this->seed(CatalogoRealSeeder::class);
        $this->seed(CatalogoRealSeeder::class);

        $this->assertSame(19, Producto::count());
        $this->assertSame(4, Combo::count());
        $this->assertSame(8, OpcionCombo::count());
        $this->assertSame(30, OpcionComboProducto::count());
    }

    public function test_soda_2_litros_solo_es_elegible_en_combos_3_y_4(): void
    {
        $this->seed(CatalogoRealSeeder::class);

        $soda2 = Producto::where('nombre', 'Soda 2 Litros')->firstOrFail();
        $combosCon2l = OpcionComboProducto::query()
            ->where('producto_id', $soda2->getKey())
            ->get()
            ->map(fn (OpcionComboProducto $link): string => $link->opcionCombo->combo->nombre)
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['Combo #3', 'Combo #4'], $combosCon2l);
    }

    public function test_todos_los_productos_reales_requieren_masa_correcta(): void
    {
        $this->seed(CatalogoRealSeeder::class);

        $pupusas = Producto::where('requiere_masa', true)->count();
        $bebidas = Producto::where('requiere_masa', false)->count();

        $this->assertSame(12, $pupusas);
        $this->assertSame(7, $bebidas);
    }

    public function test_seeder_usa_las_imagenes_empaquetadas_cuando_no_hay_env(): void
    {
        $this->seed(CatalogoRealSeeder::class);

        $this->assertSame(0, Producto::whereNull('imagen_url')->count());
        $this->assertSame(0, Combo::whereNull('imagen_url')->count());

        $producto = Producto::query()->where('nombre', 'Pupusa de Queso')->firstOrFail();
        $this->assertSame('productos/boomwalos_queso.png', $producto->imagen_url);
        $this->assertNotNull($producto->imageUrl());

        $combo = Combo::query()->where('nombre', 'Combo #2')->firstOrFail();
        $this->assertSame('combos/combo2.jpg', $combo->imagen_url);
        $this->assertNotNull($combo->imageUrl());

        Storage::disk('public')->assertExists('productos/boomwalos_queso.png');
        Storage::disk('public')->assertExists('productos/boomwalos_revuelta.jpg');
        Storage::disk('public')->assertExists('productos/coca.webp');
        Storage::disk('public')->assertExists('productos/coca-2litros.jpg');
        Storage::disk('public')->assertExists('combos/combo1.jpg');
        Storage::disk('public')->assertExists('combos/combo4.jpg');
    }

    public function test_seeder_cae_al_empaquetado_cuando_el_env_apunta_a_un_directorio_inexistente(): void
    {
        config(['pos.catalogo_images_dir' => 'C:\\carpeta\\que\\no\\existe']);

        $this->seed(CatalogoRealSeeder::class);

        $this->assertSame(19, Producto::count());
        $this->assertSame(0, Producto::whereNull('imagen_url')->count());
        Storage::disk('public')->assertExists('productos/boomwalos_queso.png');
    }
}
