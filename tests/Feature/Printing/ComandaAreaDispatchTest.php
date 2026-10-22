<?php

namespace Tests\Feature\Printing;

use App\Enums\AreaPreparacion;
use App\Enums\EstadoImpresion;
use App\Enums\TipoConexionImpresora;
use App\Enums\TipoImpresora;
use App\Enums\TipoPedido;
use App\Models\Categoria;
use App\Models\Establecimiento;
use App\Models\Impresora;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\SesionCaja;
use App\Models\User;
use App\Services\PedidoService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComandaAreaDispatchTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Establecimiento $establishment;

    private Producto $productoBar;

    private Producto $productoCocina;

    private Producto $productoPupusas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesPermissionsSeeder::class);

        $this->cashier = User::factory()->create(['usuario' => '40', 'password' => '1234']);
        $this->cashier->assignRole('cajero');

        $this->establishment = Establecimiento::create([
            'nombre' => 'Sucursal Centro',
            'direccion' => 'San Salvador',
        ]);
        $this->cashier->establecimientos()->attach($this->establishment);

        SesionCaja::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'usuario_apertura_id' => $this->cashier->getKey(),
            'monto_inicial' => 0,
            'fecha_apertura' => now(),
        ]);

        $this->productoBar = $this->product('Cerveza', AreaPreparacion::BAR);
        $this->productoCocina = $this->product('Alitas', AreaPreparacion::COCINA);
        $this->productoPupusas = $this->product('Pupusa de queso', AreaPreparacion::PUPUSAS);
    }

    public function test_it_splits_a_batch_into_one_comanda_per_area(): void
    {
        $this->actingAs($this->cashier);

        $this->printer('Bar', AreaPreparacion::BAR, $this->establishment->getKey());
        $this->printer('Cocina', AreaPreparacion::COCINA, $this->establishment->getKey());
        $this->printer('Pupusas', AreaPreparacion::PUPUSAS, $this->establishment->getKey());

        $jobs = app(PedidoService::class)->sendPendingBatch($this->order(), $this->cashier);

        $this->assertCount(3, $jobs);
        $this->assertEqualsCanonicalizing(
            ['BAR', 'COCINA', 'PUPUSAS'],
            $jobs->pluck('area')->map(fn (AreaPreparacion $area): string => $area->value)->all(),
        );

        $bar = $jobs->firstWhere('area', AreaPreparacion::BAR);

        $this->assertNotNull($bar);
        $this->assertSame('Bar', $bar->impresora?->nombre);
        $this->assertSame(EstadoImpresion::PENDIENTE, $bar->estado);
        $this->assertStringContainsString('COMANDA - BAR', $bar->contenido);
        $this->assertStringContainsString('Cerveza', $bar->contenido);
        $this->assertStringNotContainsString('Alitas', $bar->contenido);

        $cocina = $jobs->firstWhere('area', AreaPreparacion::COCINA);
        $this->assertStringContainsString('Alitas', $cocina->contenido);
        $this->assertStringNotContainsString('Cerveza', $cocina->contenido);

        $pupusas = $jobs->firstWhere('area', AreaPreparacion::PUPUSAS);
        $this->assertStringContainsString('Pupusa de queso', $pupusas->contenido);
        $this->assertStringNotContainsString('Alitas', $pupusas->contenido);
    }

    public function test_it_falls_back_to_the_default_branch_printer(): void
    {
        $this->actingAs($this->cashier);

        $this->printer('Default', null, $this->establishment->getKey());

        $jobs = app(PedidoService::class)->sendPendingBatch($this->order(), $this->cashier);

        $this->assertCount(3, $jobs);
        $this->assertTrue($jobs->every(fn ($job): bool => $job->impresora?->nombre === 'Default'));
        $this->assertTrue($jobs->every(fn ($job): bool => $job->estado === EstadoImpresion::PENDIENTE));
    }

    public function test_it_falls_back_to_any_available_comanda_printer_in_the_branch(): void
    {
        $this->actingAs($this->cashier);

        $this->printer('Pupusas', AreaPreparacion::PUPUSAS, $this->establishment->getKey());

        $jobs = app(PedidoService::class)->sendPendingBatch($this->order(), $this->cashier);

        $this->assertTrue($jobs->every(fn ($job): bool => $job->impresora?->nombre === 'Pupusas'));
        $this->assertTrue($jobs->every(fn ($job): bool => $job->estado === EstadoImpresion::PENDIENTE));
    }

    public function test_it_never_uses_a_printer_from_another_branch(): void
    {
        $this->actingAs($this->cashier);

        $otra = Establecimiento::create([
            'nombre' => 'Sucursal Norte',
            'direccion' => 'Mejicanos',
        ]);
        $this->printer('Pupusas Norte', AreaPreparacion::PUPUSAS, $otra->getKey());

        $jobs = app(PedidoService::class)->sendPendingBatch($this->order(), $this->cashier);

        $this->assertTrue($jobs->every(fn ($job): bool => $job->impresora_id === null));
        $this->assertTrue($jobs->every(fn ($job): bool => $job->estado === EstadoImpresion::ERROR));
        $this->assertStringContainsString('esta sucursal', (string) $jobs->first()->ultimo_error);
    }

    private function product(string $nombre, AreaPreparacion $area): Producto
    {
        $categoria = Categoria::create([
            'nombre' => $nombre.' cat',
            'area' => $area,
        ]);

        return Producto::create([
            'categoria_id' => $categoria->getKey(),
            'nombre' => $nombre,
            'precio' => 1.50,
            'disponibilidad' => 'DISPONIBLE',
        ]);
    }

    private function printer(string $nombre, ?AreaPreparacion $area, ?int $establecimientoId): Impresora
    {
        return Impresora::create([
            'nombre' => $nombre,
            'tipo' => TipoImpresora::COMANDA,
            'area' => $area,
            'conexion' => TipoConexionImpresora::PDF,
            'activa' => true,
            'establecimiento_id' => $establecimientoId,
        ]);
    }

    private function order(): Pedido
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier);
        $service->addProduct($pedido, $this->productoBar, $this->cashier);
        $service->addProduct($pedido, $this->productoCocina, $this->cashier);
        $service->addProduct($pedido, $this->productoPupusas, $this->cashier);

        return $pedido;
    }
}
