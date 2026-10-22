<?php

namespace Tests\Feature;

use App\Application\Catalog\SyncComboOptions;
use App\Contracts\EstablishmentContextInterface;
use App\Enums\DisponibilidadProducto;
use App\Filament\Pages\Pos\OrderEntry;
use App\Models\Categoria;
use App\Models\Combo;
use App\Models\Establecimiento;
use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CatalogoPorSucursalTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_pos_solo_muestra_el_catalogo_de_la_sucursal_actual(): void
    {
        $centro = Establecimiento::create([
            'nombre' => 'Boomwalos Centro Histórico',
            'direccion' => 'Centro Histórico',
        ]);

        $planes = Establecimiento::create([
            'nombre' => 'Los Boomwalos Planes de Renderos',
            'direccion' => 'Carretera a Panchimalco',
        ]);

        $categoriaCentro = Categoria::create([
            'nombre' => 'Pupusas Centro',
            'establecimiento_id' => $centro->getKey(),
        ]);

        $productoCentro = Producto::create([
            'establecimiento_id' => $centro->getKey(),
            'categoria_id' => $categoriaCentro->getKey(),
            'nombre' => 'Pupusa Centro',
            'precio' => 1.50,
            'disponibilidad' => DisponibilidadProducto::DISPONIBLE,
        ]);

        $comboCentro = Combo::create([
            'establecimiento_id' => $centro->getKey(),
            'nombre' => 'Combo Centro',
            'precio_fijo' => 5.00,
            'disponibilidad' => DisponibilidadProducto::DISPONIBLE,
        ]);

        $categoriaPlanes = Categoria::create([
            'nombre' => 'Pupusas Planes',
            'establecimiento_id' => $planes->getKey(),
        ]);

        $productoPlanes = Producto::create([
            'establecimiento_id' => $planes->getKey(),
            'categoria_id' => $categoriaPlanes->getKey(),
            'nombre' => 'Pupusa Planes',
            'precio' => 2.00,
            'disponibilidad' => DisponibilidadProducto::DISPONIBLE,
        ]);

        $comboPlanes = Combo::create([
            'establecimiento_id' => $planes->getKey(),
            'nombre' => 'Combo Planes',
            'precio_fijo' => 7.00,
            'disponibilidad' => DisponibilidadProducto::DISPONIBLE,
        ]);

        app(EstablishmentContextInterface::class)->set($centro->getKey());

        $page = app(OrderEntry::class);
        $page->category = 'all';
        $page->search = '';

        $productos = $page->getProductsProperty();
        $combos = $page->getCombosProperty();
        $categorias = $page->getRootCategoriesProperty();

        $this->assertTrue($productos->contains('id', $productoCentro->getKey()));
        $this->assertFalse($productos->contains('id', $productoPlanes->getKey()));

        $this->assertTrue($combos->contains('id', $comboCentro->getKey()));
        $this->assertFalse($combos->contains('id', $comboPlanes->getKey()));

        $this->assertTrue($categorias->contains('id', $categoriaCentro->getKey()));
        $this->assertFalse($categorias->contains('id', $categoriaPlanes->getKey()));
    }

    public function test_un_combo_no_puede_usar_productos_de_otra_sucursal(): void
    {
        $centro = Establecimiento::create([
            'nombre' => 'Boomwalos Centro Histórico',
            'direccion' => 'Centro Histórico',
        ]);

        $planes = Establecimiento::create([
            'nombre' => 'Los Boomwalos Planes de Renderos',
            'direccion' => 'Carretera a Panchimalco',
        ]);

        $categoriaPlanes = Categoria::create([
            'nombre' => 'Pupusas Planes',
            'establecimiento_id' => $planes->getKey(),
        ]);

        $productoPlanes = Producto::create([
            'establecimiento_id' => $planes->getKey(),
            'categoria_id' => $categoriaPlanes->getKey(),
            'nombre' => 'Pupusa Planes',
            'precio' => 2.00,
            'disponibilidad' => DisponibilidadProducto::DISPONIBLE,
        ]);

        $comboCentro = Combo::create([
            'establecimiento_id' => $centro->getKey(),
            'nombre' => 'Combo Centro',
            'precio_fijo' => 5.00,
            'disponibilidad' => DisponibilidadProducto::DISPONIBLE,
        ]);

        $this->expectException(ValidationException::class);

        app(SyncComboOptions::class)->handle($comboCentro, [
            [
                'nombre' => 'Pupusas',
                'cantidad_requerida' => 1,
                'producto_ids' => [$productoPlanes->getKey()],
            ],
        ]);
    }
}
