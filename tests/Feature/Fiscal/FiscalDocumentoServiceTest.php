<?php

namespace Tests\Feature\Fiscal;

use App\Application\Fiscal\FiscalOutboxService;
use App\Enums\EstadoDocumentoFiscal;
use App\Enums\EstadoMesa;
use App\Enums\EstadoVentaFiscal;
use App\Enums\MetodoPago;
use App\Enums\TipoDocumento;
use App\Enums\TipoPedido;
use App\Enums\ZonaMesa;
use App\Jobs\EnviarVentasFiscalesJob;
use App\Models\Categoria;
use App\Models\ConfiguracionFiscal;
use App\Models\DocumentoFiscal;
use App\Models\Establecimiento;
use App\Models\Impresora;
use App\Models\Mesa;
use App\Models\Producto;
use App\Models\SesionCaja;
use App\Models\User;
use App\Models\VentaFiscalPos;
use App\Services\CobroService;
use App\Services\FiscalDocumentoService;
use App\Services\PedidoService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FiscalDocumentoServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $cashier;

    private Establecimiento $establishment;

    private Mesa $table;

    private Producto $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesPermissionsSeeder::class);

        $this->admin = User::factory()->create([
            'usuario' => '1',
            'password' => '1234',
        ]);
        $this->admin->assignRole('administrador');

        $this->cashier = User::factory()->create([
            'usuario' => '21',
            'password' => '1234',
        ]);
        $this->cashier->assignRole('cajero');

        $this->establishment = Establecimiento::create([
            'nombre' => 'Los Boomwalos',
            'direccion' => 'Dirección de prueba',
        ]);

        $this->table = Mesa::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'numero' => '8',
            'zona' => ZonaMesa::SALON,
            'estado' => EstadoMesa::LIBRE,
        ]);

        $category = Categoria::create(['nombre' => 'Bebidas Frías']);
        $this->product = Producto::create([
            'categoria_id' => $category->getKey(),
            'nombre' => 'Limonada fresca',
            'precio' => 4,
            'disponibilidad' => 'DISPONIBLE',
        ]);

        SesionCaja::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'usuario_apertura_id' => $this->cashier->getKey(),
            'monto_inicial' => 0,
            'fecha_apertura' => now(),
        ]);

        Impresora::create([
            'nombre' => 'Cocina',
            'tipo' => 'COMANDA',
            'configuracion' => ['driver' => 'queue'],
        ]);

        ConfiguracionFiscal::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'fiscal_habilitada' => true,
            'cliente_key' => 'est-test',
            'cliente_secret' => (string) config('fiscal.mock.secret'),
            'intentos_maximos' => 3,
        ]);
    }

    private function sincronizedVenta(): VentaFiscalPos
    {
        Queue::fake();
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::MESA, $this->cashier, $this->table->getKey());
        $service->addProduct($pedido, $this->product, $this->cashier);
        $service->sendPendingBatch($pedido, $this->cashier);

        $pago = app(CobroService::class)->charge(
            $pedido,
            MetodoPago::EFECTIVO,
            '10.00',
            $this->cashier,
        );

        $venta = VentaFiscalPos::where('pago_id', $pago->getKey())->firstOrFail();

        Http::fake([
            '*' => Http::response([
                'fiscal_sale_id' => 'MOCK-SOL-202',
                'estado' => 'RECIBIDA',
                'qr_url' => null,
            ], 202),
        ]);

        (new EnviarVentasFiscalesJob($venta->getKey()))->handle(app(FiscalOutboxService::class));

        return $venta->fresh();
    }

    private function datosSolicitante(array $overrides = []): array
    {
        return [
            'nombre' => 'Cliente Final',
            'documento' => '06143402-1',
            'tipo_documento' => 'NIT',
            ...$overrides,
        ];
    }

    public function test_solicitar_crea_documento_pendiente_con_expiracion_de_48_horas(): void
    {
        Queue::fake();
        $venta = $this->sincronizedVenta();

        $documento = app(FiscalDocumentoService::class)->solicitar(
            $venta,
            TipoDocumento::FACTURA,
            $this->datosSolicitante(),
            $this->admin,
        );

        $this->assertSame(EstadoDocumentoFiscal::PENDIENTE, $documento->estado);
        $this->assertSame($venta->getKey(), $documento->venta_fiscal_pos_id);
        $this->assertSame('Cliente Final', $documento->datos_solicitante['nombre']);
        $this->assertNull($documento->numero_control);
        $this->assertNull($documento->codigo_generacion);
        $this->assertNull($documento->sello_recepcion);
        $this->assertNotNull($documento->solicitado_at);
        $this->assertTrue($documento->isSolicitable());
        $this->assertTrue(abs($documento->expires_at->diffInMinutes($documento->solicitado_at)) >= 2870);
        $this->assertTrue(abs($documento->expires_at->diffInMinutes($documento->solicitado_at)) <= 2890);
        $this->assertDatabaseHas('documento_fiscales', [
            'pedido_id' => $venta->pedido_id,
            'venta_fiscal_pos_id' => $venta->getKey(),
            'tipo_documento' => TipoDocumento::FACTURA->value,
        ]);
    }

    public function test_solicitar_exige_permiso_solicitar_documento_fiscal(): void
    {
        $venta = $this->sincronizedVenta();

        $this->expectException(AuthorizationException::class);

        app(FiscalDocumentoService::class)->solicitar(
            $venta,
            TipoDocumento::CCF,
            $this->datosSolicitante(),
            $this->cashier,
        );
    }

    public function test_solicitar_rechaza_venta_no_sincronizada(): void
    {
        Queue::fake();
        $venta = $this->sincronizedVenta();
        $venta->update(['estado' => EstadoVentaFiscal::NO->value]);

        $this->expectException(ValidationException::class);

        app(FiscalDocumentoService::class)->solicitar(
            $venta,
            TipoDocumento::FACTURA,
            $this->datosSolicitante(),
            $this->admin,
        );
    }

    public function test_solicitar_rechaza_datos_solicitante_incompletos(): void
    {
        $venta = $this->sincronizedVenta();

        $this->expectException(ValidationException::class);

        app(FiscalDocumentoService::class)->solicitar(
            $venta,
            TipoDocumento::FACTURA,
            $this->datosSolicitante(['documento' => '']),
            $this->admin,
        );
    }

    public function test_solicitar_rechaza_solicitud_vigente_duplicada(): void
    {
        $venta = $this->sincronizedVenta();
        $service = app(FiscalDocumentoService::class);

        $service->solicitar($venta, TipoDocumento::FACTURA, $this->datosSolicitante(), $this->admin);

        $this->expectException(ValidationException::class);

        $service->solicitar($venta, TipoDocumento::FACTURA, $this->datosSolicitante(), $this->admin);
    }

    public function test_resolicitud_tras_expiracion_resetea_el_mismo_registro(): void
    {
        $venta = $this->sincronizedVenta();
        $service = app(FiscalDocumentoService::class);

        $original = $service->solicitar($venta, TipoDocumento::CCF, $this->datosSolicitante(), $this->admin);

        $original->update([
            'expires_at' => now()->subMinutes(5),
            'codigo_generacion' => 'RESPALDO-1',
            'numero_control' => 'DTE-01',
        ]);

        $renovado = $service->solicitar(
            $venta,
            TipoDocumento::CCF,
            $this->datosSolicitante(['nombre' => 'Cliente Renovado']),
            $this->admin,
        );

        $this->assertSame($original->getKey(), $renovado->getKey());
        $this->assertSame(EstadoDocumentoFiscal::PENDIENTE, $renovado->estado);
        $this->assertSame('Cliente Renovado', $renovado->datos_solicitante['nombre']);
        $this->assertNull($renovado->codigo_generacion);
        $this->assertNull($renovado->numero_control);
        $this->assertTrue($renovado->isSolicitable());
        $this->assertTrue(abs($renovado->expires_at->diffInMinutes($renovado->solicitado_at)) >= 2870);
        $this->assertTrue(abs($renovado->expires_at->diffInMinutes($renovado->solicitado_at)) <= 2890);
    }
}

