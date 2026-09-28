<?php

namespace Tests\Feature\Console;

use App\Enums\DisponibilidadProducto;
use App\Enums\EstadoComercialPedido;
use App\Enums\MetodoPago;
use App\Enums\TipoConexionImpresora;
use App\Enums\TipoImpresora;
use App\Enums\TipoPedido;
use App\Models\Categoria;
use App\Models\Combo;
use App\Models\DetallePedido;
use App\Models\Establecimiento;
use App\Models\Impresora;
use App\Models\OpcionCombo;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\SesionCaja;
use App\Models\TrabajoImpresion;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PosTicketDemoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Establecimiento $establishment;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seed(RolesPermissionsSeeder::class);

        $this->admin = User::factory()->create([
            'usuario' => 'admin',
            'password' => 'admin1234',
            'nombre' => 'Administrador General',
        ]);
        $this->admin->assignRole('administrador');

        $this->establishment = Establecimiento::create([
            'nombre' => 'Pupusería Demo',
            'direccion' => 'Dirección de prueba',
        ]);

        $this->seedCatalog();

        SesionCaja::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'usuario_apertura_id' => $this->admin->getKey(),
            'monto_inicial' => 0,
            'fecha_apertura' => now(),
        ]);

        Impresora::create([
            'nombre' => 'Cajero Virtual',
            'tipo' => TipoImpresora::TICKET,
            'conexion' => TipoConexionImpresora::PDF,
            'activa' => true,
        ]);
    }

    public function test_demo_builds_a_real_order_and_prints_its_pdf(): void
    {
        $this->artisan('pos:ticket-demo', ['--establecimiento' => $this->establishment->getKey()])
            ->assertExitCode(0);

        $this->assertDatabaseHas('pedidos', [
            'establecimiento_id' => $this->establishment->getKey(),
            'tipo_pedido' => TipoPedido::PARA_LLEVAR->value,
            'estado_comercial' => EstadoComercialPedido::COBRADO->value,
        ]);

        $pedido = Pedido::query()->firstOrFail();
        $this->assertSame(15.15, round($pedido->total(), 2));

        $this->assertDatabaseHas('pagos', [
            'pedido_id' => $pedido->getKey(),
            'metodo_pago' => MetodoPago::EFECTIVO->value,
            'monto_recibido' => '20.00',
            'cambio_devuelto' => '4.85',
        ]);

        $job = TrabajoImpresion::query()
            ->where('tipo_trabajo', 'TICKET')
            ->firstOrFail();

        $this->assertSame('IMPRESO', $job->estado->value);
        $this->assertFalse((bool) $job->es_reimpresion);
        $this->assertStringStartsWith('demo-', $job->original_uid);

        Storage::disk('public')->assertExists("impresiones/trabajo-{$job->getKey()}.pdf");
        $this->assertStringStartsWith('%PDF', Storage::disk('public')->get("impresiones/trabajo-{$job->getKey()}.pdf"));
    }

    public function test_ticket_content_matches_the_real_catalog_rendering(): void
    {
        $this->artisan('pos:ticket-demo', ['--establecimiento' => $this->establishment->getKey()]);

        $contenido = TrabajoImpresion::query()->where('tipo_trabajo', 'TICKET')->value('contenido');

        $this->assertStringContainsString('PUPUSERÍA DEMO', $contenido);
        $this->assertStringContainsString('TICKET DE CLIENTE', $contenido);
        $this->assertStringContainsString('PARA LLEVAR · MOSTRADOR', $contenido);
        $this->assertStringContainsString('1 x Pupusa de Queso', $contenido);
        $this->assertStringContainsString('  Masa: Arroz', $contenido);
        $this->assertStringContainsString('1 x Horchata', $contenido);
        $this->assertStringContainsString('Combo #2', $contenido);
        $this->assertStringContainsString('- 4 Pupusa de Queso · Arroz', $contenido);
        $this->assertStringContainsString('- 4 Pupusa Revuelta · Maíz', $contenido);
        $this->assertStringContainsString('- 2 Soda', $contenido);
        $this->assertStringContainsString('- 1 Kolashampagne', $contenido);
        $this->assertStringContainsString('TOTAL  $15.15', $contenido);
        $this->assertStringContainsString('PAGO   Efectivo', $contenido);
        $this->assertStringContainsString('RECIBIDO $20.00', $contenido);
        $this->assertStringContainsString('CAMBIO  $4.85', $contenido);
        $this->assertStringContainsString('ATENDIDO POR: Administrador General', $contenido);
        $this->assertStringContainsString('¿DESEA FACTURA O CCF?', $contenido);
    }

    public function test_solo_texto_prints_content_without_creating_jobs(): void
    {
        $this->artisan('pos:ticket-demo', [
            '--establecimiento' => $this->establishment->getKey(),
            '--solo-texto' => true,
        ])->assertExitCode(0)->expectsOutputToContain('TICKET DE CLIENTE');

        $this->assertDatabaseCount('trabajo_impresion', 0);
    }

    public function test_reprint_of_an_existing_pedido_creates_a_reimpresion_pdf(): void
    {
        $pedido = $this->chargedOrder();

        $this->artisan('pos:ticket-demo', [
            '--pedido' => $pedido->getKey(),
            '--establecimiento' => $this->establishment->getKey(),
        ])->assertExitCode(0);

        $before = Pedido::query()->count();

        $this->assertSame(1, $before);

        $job = TrabajoImpresion::query()->where('tipo_trabajo', 'TICKET')->firstOrFail();

        $this->assertSame($pedido->getKey(), $job->pedido_id);
        $this->assertSame('IMPRESO', $job->estado->value);
        $this->assertTrue((bool) $job->es_reimpresion);
        $this->assertSame('Ticket de muestra generado por pos:ticket-demo', $job->motivo_reimpresion);

        Storage::disk('public')->assertExists("impresiones/trabajo-{$job->getKey()}.pdf");
    }

    public function test_fails_friendly_when_no_ticket_printer(): void
    {
        Impresora::query()->delete();

        $this->artisan('pos:ticket-demo', ['--establecimiento' => $this->establishment->getKey()])
            ->assertExitCode(1)
            ->expectsOutputToContain('No hay impresora de ticket configurada');

        $this->assertDatabaseCount('trabajo_impresion', 0);
    }

    public function test_fails_friendly_when_establishment_is_ambiguous(): void
    {
        Establecimiento::create([
            'nombre' => 'Segunda Sucursal',
            'direccion' => 'Otra dirección',
        ]);

        $this->artisan('pos:ticket-demo')
            ->assertExitCode(1)
            ->expectsOutputToContain('--establecimiento=<id>');
    }

    public function test_fails_friendly_when_existing_pedido_does_not_exist(): void
    {
        $this->artisan('pos:ticket-demo', [
            '--pedido' => 999999,
            '--establecimiento' => $this->establishment->getKey(),
        ])->assertExitCode(1)
            ->expectsOutputToContain('El pedido indicado no existe');
    }

    private function chargedOrder(): Pedido
    {
        $categoria = Categoria::query()->create(['nombre' => 'Bebidas']);
        $product = Producto::create([
            'categoria_id' => $categoria->getKey(),
            'nombre' => 'Jugo de mango',
            'precio' => 3,
            'disponibilidad' => DisponibilidadProducto::DISPONIBLE,
        ]);
        $sesion = SesionCaja::query()->where('establecimiento_id', $this->establishment->getKey())->firstOrFail();

        $pedido = Pedido::create([
            'numero_seguimiento' => 'BW-TEST-REIMPRESION',
            'tipo_pedido' => TipoPedido::PARA_LLEVAR,
            'establecimiento_id' => $this->establishment->getKey(),
            'usuario_id' => $this->admin->getKey(),
            'origen_pedido' => 'CAJA',
            'codigo_corto' => 999,
            'fecha_codigo' => now()->toDateString(),
            'estado_comercial' => EstadoComercialPedido::COBRADO,
        ]);

        DetallePedido::create([
            'pedido_id' => $pedido->getKey(),
            'estado_linea' => 'ACTIVA',
            'producto_id' => $product->getKey(),
            'cantidad' => 2,
            'precio_unitario' => $product->precio,
        ]);

        Pago::create([
            'pedido_id' => $pedido->getKey(),
            'sesion_caja_id' => $sesion->getKey(),
            'metodo_pago' => MetodoPago::EFECTIVO,
            'monto_recibido' => '7.00',
            'cambio_devuelto' => '1.00',
        ]);

        return $pedido;
    }

    private function seedCatalog(): void
    {
        $categoria = Categoria::query()->create(['nombre' => 'Pupusas']);

        $pupusaQueso = Producto::create([
            'categoria_id' => $categoria->getKey(),
            'nombre' => 'Pupusa de Queso',
            'precio' => 1.40,
            'disponibilidad' => DisponibilidadProducto::DISPONIBLE,
            'requiere_masa' => true,
        ]);

        $pupusaRevuelta = Producto::create([
            'categoria_id' => $categoria->getKey(),
            'nombre' => 'Pupusa Revuelta',
            'precio' => 1.25,
            'disponibilidad' => DisponibilidadProducto::DISPONIBLE,
            'requiere_masa' => true,
        ]);

        $horchata = Producto::create([
            'categoria_id' => $categoria->getKey(),
            'nombre' => 'Horchata',
            'precio' => 2.00,
            'disponibilidad' => DisponibilidadProducto::DISPONIBLE,
        ]);

        $soda = Producto::create([
            'categoria_id' => $categoria->getKey(),
            'nombre' => 'Soda',
            'precio' => 1.00,
            'disponibilidad' => DisponibilidadProducto::DISPONIBLE,
        ]);

        $kolashampagne = Producto::create([
            'categoria_id' => $categoria->getKey(),
            'nombre' => 'Kolashampagne',
            'precio' => 0.75,
            'disponibilidad' => DisponibilidadProducto::DISPONIBLE,
        ]);

        $combo = Combo::create([
            'nombre' => 'Combo #2',
            'precio_fijo' => 11.75,
            'disponibilidad' => DisponibilidadProducto::DISPONIBLE,
        ]);

        $pupusas = OpcionCombo::create([
            'combo_id' => $combo->getKey(),
            'nombre' => 'Pupusas',
            'cantidad_requerida' => 8,
            'es_obligatorio' => true,
        ]);
        $pupusas->productos()->attach([$pupusaQueso->getKey(), $pupusaRevuelta->getKey()]);

        $sodas = OpcionCombo::create([
            'combo_id' => $combo->getKey(),
            'nombre' => 'Sodas',
            'cantidad_requerida' => 3,
            'es_obligatorio' => true,
        ]);
        $sodas->productos()->attach([$soda->getKey(), $kolashampagne->getKey()]);
    }
}
