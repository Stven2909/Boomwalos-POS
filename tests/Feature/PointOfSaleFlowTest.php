<?php

namespace Tests\Feature;

use App\Enums\EstadoComercialPedido;
use App\Enums\EstadoLineaPedido;
use App\Enums\EstadoMesa;
use App\Enums\EstadoCocina;
use App\Enums\MetodoPago;
use App\Enums\OrigenPedido;
use App\Enums\TipoPedido;
use App\Enums\ZonaMesa;
use App\Filament\Pages\Pos\ChargeOrder;
use App\Filament\Pages\Pos\ServiceSelection;
use App\Filament\Pages\Pos\TableSelection;
use App\Models\Categoria;
use App\Models\Combo;
use App\Models\Establecimiento;
use App\Models\Impresora;
use App\Models\Mesa;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\SesionCaja;
use App\Models\User;
use App\Enums\TipoImpresora;
use App\Services\PedidoService;
use App\Services\CobroService;
use App\Services\KitchenService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PointOfSaleFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Establecimiento $establishment;

    private Mesa $table;

    private Producto $product;

    private Producto $secondProduct;

    private Producto $thirdProduct;

    private Combo $combo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesPermissionsSeeder::class);

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

        $this->secondProduct = Producto::create([
            'categoria_id' => $category->getKey(),
            'nombre' => 'Pupusa revuelta de prueba',
            'precio' => 1.75,
            'disponibilidad' => 'DISPONIBLE',
        ]);

        $this->thirdProduct = Producto::create([
            'categoria_id' => $category->getKey(),
            'nombre' => 'Pupusa especial de prueba',
            'precio' => 2.00,
            'disponibilidad' => 'DISPONIBLE',
        ]);

        $this->combo = Combo::create([
            'nombre' => 'Combo de prueba',
            'precio_fijo' => 15,
            'disponibilidad' => 'DISPONIBLE',
        ]);

        $this->combo->opcionesCombo()->create([
            'nombre' => 'Pupusas',
            'cantidad_requerida' => 10,
            'es_obligatorio' => true,
        ])->productos()->sync([
            $this->product->getKey(),
            $this->secondProduct->getKey(),
            $this->thirdProduct->getKey(),
        ]);

        SesionCaja::create([
            'establecimiento_id' => $this->establishment->getKey(),
            'usuario_apertura_id' => $this->cashier->getKey(),
            'monto_inicial' => 0,
            'fecha_apertura' => now(),
        ]);

        Impresora::create([
            'nombre' => 'Cocina',
            'tipo' => TipoImpresora::COMANDA,
            'configuracion' => ['driver' => 'queue'],
        ]);
    }

    public function test_cashier_can_open_a_table_add_a_product_and_send_a_batch(): void
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::MESA, $this->cashier, $this->table->getKey());
        $detail = $service->addProduct($pedido, $this->product, $this->cashier);
        $batch = $service->sendPendingBatch($pedido, $this->cashier);

        $this->assertSame(1, $batch->numero_tanda);
        $this->assertSame($batch->getKey(), $detail->fresh()->tanda_id);
        $this->assertDatabaseHas('mesas', [
            'id' => $this->table->getKey(),
            'estado' => EstadoMesa::OCUPADA->value,
        ]);
        $this->assertDatabaseHas('evento_auditorias', [
            'entidad_id' => $pedido->getKey(),
            'tipo_evento' => 'pedido_enviado_cocina',
        ]);
        $printJob = $batch->fresh()->trabajosImpresion()->first();
        $this->assertNotNull($printJob);
        $this->assertSame('PENDIENTE', $printJob->estado->value);
        $this->assertStringContainsString('Limonada fresca', $printJob->contenido);
    }

    public function test_selecting_an_occupied_table_reuses_the_open_order(): void
    {
        $existing = Pedido::create([
            'numero_seguimiento' => 'BW-TEST-0001',
            'tipo_pedido' => TipoPedido::MESA,
            'mesa_id' => $this->table->getKey(),
            'establecimiento_id' => $this->establishment->getKey(),
            'usuario_id' => $this->cashier->getKey(),
            'estado_comercial' => EstadoComercialPedido::ABIERTO,
        ]);
        $this->table->update(['estado' => EstadoMesa::OCUPADA]);

        $reused = app(PedidoService::class)->startOrder(TipoPedido::MESA, $this->cashier, $this->table->getKey());

        $this->assertSame($existing->getKey(), $reused->getKey());
        $this->assertDatabaseCount('pedidos', 1);
    }

    public function test_pending_products_can_be_removed_and_restored(): void
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::MESA, $this->cashier, $this->table->getKey());
        $detail = $service->addProduct($pedido, $this->product, $this->cashier);

        $service->removePendingLine($pedido, $detail);
        $this->assertDatabaseMissing('detalles_pedido', ['id' => $detail->getKey()]);

        $restored = $service->restorePendingLine($pedido, $this->product->getKey(), 1, '4.00');

        $this->assertSame(EstadoLineaPedido::ACTIVA, $restored->estado_linea);
        $this->assertNull($restored->tanda_id);
    }

    public function test_point_of_sale_redirects_to_cash_opening_without_an_active_session(): void
    {
        SesionCaja::query()->delete();

        $this->actingAs($this->cashier)
            ->get(ServiceSelection::getUrl())
            ->assertRedirect('/admin/caja/abrir');
    }

    public function test_combo_accepts_a_mixed_selection_and_keeps_different_combinations_separate(): void
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::MESA, $this->cashier, $this->table->getKey());
        $optionId = $this->combo->opcionesCombo()->value('id');

        $firstSelection = [
            (string) $optionId => [
                (string) $this->product->getKey() => 4,
                (string) $this->secondProduct->getKey() => 3,
                (string) $this->thirdProduct->getKey() => 3,
            ],
        ];

        $firstLine = $service->addCombo($pedido, $this->combo, $firstSelection, $this->cashier);
        $sameLine = $service->addCombo($pedido, $this->combo, $firstSelection, $this->cashier);

        $differentSelection = [
            (string) $optionId => [
                (string) $this->product->getKey() => 5,
                (string) $this->secondProduct->getKey() => 5,
            ],
        ];
        $differentLine = $service->addCombo($pedido, $this->combo, $differentSelection, $this->cashier);

        $this->assertSame($firstLine->getKey(), $sameLine->getKey());
        $this->assertNotSame($firstLine->getKey(), $differentLine->getKey());
        $this->assertSame(2, $firstLine->fresh()->cantidad);
        $this->assertSame(2, $pedido->fresh()->detalles()->whereNotNull('combo_id')->count());
        $this->assertEquals(45.00, $pedido->fresh()->total());
    }

    public function test_combo_rejects_an_incomplete_selection_and_second_send_does_not_duplicate_batch(): void
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::MESA, $this->cashier, $this->table->getKey());
        $optionId = $this->combo->opcionesCombo()->value('id');

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->addCombo($pedido, $this->combo, [
            (string) $optionId => [(string) $this->product->getKey() => 9],
        ], $this->cashier);
    }

    public function test_resending_without_new_lines_returns_the_existing_batch(): void
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::MESA, $this->cashier, $this->table->getKey());
        $service->addProduct($pedido, $this->product, $this->cashier);

        $firstBatch = $service->sendPendingBatch($pedido, $this->cashier);
        $sameBatch = $service->sendPendingBatch($pedido, $this->cashier);

        $this->assertSame($firstBatch->getKey(), $sameBatch->getKey());
        $this->assertDatabaseCount('tandas_pedido', 1);
    }

    public function test_new_pending_lines_after_a_first_send_create_a_second_batch(): void
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::MESA, $this->cashier, $this->table->getKey());

        $firstDetail = $service->addProduct($pedido, $this->product, $this->cashier);
        $firstBatch = $service->sendPendingBatch($pedido, $this->cashier);
        $secondDetail = $service->addProduct($pedido, $this->product, $this->cashier);
        $secondBatch = $service->sendPendingBatch($pedido, $this->cashier);

        $this->assertNotSame($firstBatch->getKey(), $secondBatch->getKey());
        $this->assertSame(2, $secondBatch->numero_tanda);
        $this->assertSame($firstBatch->getKey(), $firstDetail->fresh()->tanda_id);
        $this->assertSame($secondBatch->getKey(), $secondDetail->fresh()->tanda_id);
        $this->assertDatabaseCount('tandas_pedido', 2);
    }

    public function test_cash_payment_registers_change_and_keeps_the_table_occupied(): void
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::MESA, $this->cashier, $this->table->getKey());
        $service->addProduct($pedido, $this->product, $this->cashier);
        $service->sendPendingBatch($pedido, $this->cashier);

        $payment = app(CobroService::class)->charge(
            $pedido,
            MetodoPago::EFECTIVO,
            '10.00',
            $this->cashier,
        );

        $this->assertSame(MetodoPago::EFECTIVO, $payment->metodo_pago);
        $this->assertEquals('10.00', $payment->monto_recibido);
        $this->assertEquals('6.00', $payment->cambio_devuelto);
        $this->assertDatabaseHas('pedidos', [
            'id' => $pedido->getKey(),
            'estado_comercial' => EstadoComercialPedido::COBRADO->value,
        ]);
        $this->assertDatabaseHas('mesas', [
            'id' => $this->table->getKey(),
            'estado' => EstadoMesa::OCUPADA->value,
        ]);
        $this->assertDatabaseHas('evento_auditorias', [
            'entidad_id' => $pedido->getKey(),
            'tipo_evento' => 'pedido_cobrado',
        ]);
    }

    public function test_card_payment_uses_the_exact_total(): void
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::MESA, $this->cashier, $this->table->getKey());
        $service->addProduct($pedido, $this->product, $this->cashier);
        $service->sendPendingBatch($pedido, $this->cashier);

        $payment = app(CobroService::class)->charge(
            $pedido,
            MetodoPago::TARJETA,
            null,
            $this->cashier,
            ['aprobada' => true, 'referencia' => 'TEST-REF'],
        );

        $this->assertEquals('4.00', $payment->monto_recibido);
        $this->assertEquals('0.00', $payment->cambio_devuelto);
    }

    public function test_pending_lines_block_payment(): void
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::MESA, $this->cashier, $this->table->getKey());
        $service->addProduct($pedido, $this->product, $this->cashier);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(CobroService::class)->charge($pedido, MetodoPago::EFECTIVO, '4.00', $this->cashier);
    }

    public function test_second_payment_is_rejected_and_does_not_duplicate_payment(): void
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::MESA, $this->cashier, $this->table->getKey());
        $service->addProduct($pedido, $this->product, $this->cashier);
        $service->sendPendingBatch($pedido, $this->cashier);
        app(CobroService::class)->charge($pedido, MetodoPago::TARJETA, null, $this->cashier, ['aprobada' => true, 'referencia' => 'TEST-REF']);

        try {
            app(CobroService::class)->charge($pedido, MetodoPago::TARJETA, null, $this->cashier, ['aprobada' => true, 'referencia' => 'TEST-REF']);
            $this->fail('El segundo cobro debía ser rechazado.');
        } catch (\Illuminate\Validation\ValidationException) {
            // La cuenta debe permanecer con un solo pago.
        }

        $this->assertDatabaseCount('pagos', 1);
    }

    public function test_cobrado_order_cannot_receive_new_products(): void
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::MESA, $this->cashier, $this->table->getKey());
        $service->addProduct($pedido, $this->product, $this->cashier);
        $service->sendPendingBatch($pedido, $this->cashier);
        app(CobroService::class)->charge($pedido, MetodoPago::TARJETA, null, $this->cashier, ['aprobada' => true, 'referencia' => 'TEST-REF']);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->addProduct($pedido, $this->secondProduct, $this->cashier);
    }

    public function test_cashier_can_access_operational_table_map(): void
    {
        $this->actingAs($this->cashier)
            ->get(TableSelection::getUrl([
                'tipo' => TipoPedido::MESA->value,
                'entrada' => 'mesas',
            ]))
            ->assertSuccessful();

        $this->actingAs($this->cashier)
            ->get(ChargeOrder::getUrl(['pedido' => 999999]))
            ->assertNotFound();
    }

    public function test_start_order_assigns_incrementing_short_code_per_day(): void
    {
        $service = app(PedidoService::class);

        $first = $service->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier);
        $second = $service->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier);

        $this->assertSame(OrigenPedido::CAJA, $first->origen_pedido);
        $this->assertSame(1, $first->codigo_corto);
        $this->assertSame(now()->toDateString(), $first->fecha_codigo?->toDateString());
        $this->assertSame(2, $second->codigo_corto);
        $this->assertDatabaseHas('secuencias_pedidos', [
            'establecimiento_id' => $this->establishment->getKey(),
            'fecha' => now()->toDateString(),
            'ultimo_valor' => 2,
        ]);
    }

    public function test_device_order_sends_to_cash_register_without_kitchen_batch(): void
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier, null, OrigenPedido::DISPOSITIVO);
        $service->addProduct($pedido, $this->product, $this->cashier);

        $service->sendToCashRegister($pedido, $this->cashier);

        $pedido->refresh();
        $this->assertSame(OrigenPedido::DISPOSITIVO, $pedido->origen_pedido);
        $this->assertSame(EstadoComercialPedido::PENDIENTE_COBRO, $pedido->estado_comercial);
        $this->assertDatabaseCount('tandas_pedido', 0);
        $this->assertDatabaseCount('pagos', 0);
        $this->assertDatabaseMissing('evento_auditorias', [
            'entidad_id' => $pedido->getKey(),
            'tipo_evento' => 'pedido_enviado_cocina',
        ]);
        $this->assertDatabaseHas('evento_auditorias', [
            'entidad_id' => $pedido->getKey(),
            'tipo_evento' => 'pedido_enviado_caja',
        ]);
    }

    public function test_sending_the_same_order_to_cash_register_twice_is_rejected(): void
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier, null, OrigenPedido::DISPOSITIVO);
        $service->addProduct($pedido, $this->product, $this->cashier);
        $service->sendToCashRegister($pedido, $this->cashier);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->sendToCashRegister($pedido, $this->cashier);
    }

    public function test_pendiente_cobro_order_cannot_receive_new_products(): void
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier, null, OrigenPedido::DISPOSITIVO);
        $service->addProduct($pedido, $this->product, $this->cashier);
        $service->sendToCashRegister($pedido, $this->cashier);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->addProduct($pedido, $this->secondProduct, $this->cashier);
    }

    public function test_cashier_can_charge_a_device_order_pending_in_cash_register(): void
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier, null, OrigenPedido::DISPOSITIVO);
        $service->addProduct($pedido, $this->product, $this->cashier);
        $service->sendToCashRegister($pedido, $this->cashier);

        [$payment, $tanda] = app(CobroService::class)->chargeAndSend(
            $pedido,
            MetodoPago::TARJETA,
            null,
            $this->cashier,
            ['aprobada' => true, 'referencia' => 'TEST-REF'],
        );

        $this->assertSame($pedido->getKey(), $payment->pedido_id);
        $this->assertNotNull($tanda);
        $this->assertSame(1, $tanda->numero_tanda);
        $this->assertDatabaseHas('pedidos', [
            'id' => $pedido->getKey(),
            'estado_comercial' => EstadoComercialPedido::COBRADO->value,
        ]);
        $this->assertDatabaseHas('tandas_pedido', [
            'id' => $tanda->getKey(),
            'estado_cocina' => EstadoCocina::PENDIENTE->value,
        ]);
    }

    public function test_cashier_can_access_pending_orders_page(): void
    {
        $this->actingAs($this->cashier)
            ->get(\App\Filament\Pages\Pos\ListaPedidos::getUrl())
            ->assertSuccessful();

        $this->actingAs($this->cashier)
            ->get(\App\Filament\Pages\Pos\ServiceSelection::getUrl())
            ->assertSuccessful();
    }

    public function test_order_and_charge_views_render_for_a_valid_pedido(): void
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier);
        $service->addProduct($pedido, $this->product, $this->cashier);
        $service->sendPendingBatch($pedido, $this->cashier);

        $this->actingAs($this->cashier)
            ->get(\App\Filament\Pages\Pos\OrderEntry::getUrl(['pedido' => $pedido->getKey()]))
            ->assertSuccessful();

        $this->actingAs($this->cashier)
            ->get(\App\Filament\Pages\Pos\ChargeOrder::getUrl(['pedido' => $pedido->getKey()]))
            ->assertSuccessful();
    }

    public function test_cashier_pending_orders_page_lists_orders_waiting_to_be_charged(): void
    {
        $service = app(PedidoService::class);
        $deviceOrder = $service->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier, null, OrigenPedido::DISPOSITIVO);
        $service->addProduct($deviceOrder, $this->product, $this->cashier);
        $service->sendToCashRegister($deviceOrder, $this->cashier);

        $page = app(\App\Filament\Pages\Pos\ListaPedidos::class);

        $this->assertTrue($page->orders->contains('id', $deviceOrder->getKey()));
        $this->assertSame(4.00, $page->orderTotal($deviceOrder->fresh(['detalles'])));
    }

    public function test_charge_and_send_creates_one_payment_and_one_batch(): void
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier);
        $detail = $service->addProduct($pedido, $this->product, $this->cashier);

        [$payment, $tanda] = app(CobroService::class)->chargeAndSend(
            $pedido,
            MetodoPago::EFECTIVO,
            '10.00',
            $this->cashier,
        );

        $this->assertSame($pedido->getKey(), $payment->pedido_id);
        $this->assertNotNull($tanda);
        $this->assertSame(1, $tanda->numero_tanda);
        $this->assertSame($tanda->getKey(), $detail->fresh()->tanda_id);
        $this->assertDatabaseCount('pagos', 1);
        $this->assertDatabaseCount('tandas_pedido', 1);
        $this->assertDatabaseHas('pedidos', [
            'id' => $pedido->getKey(),
            'estado_comercial' => EstadoComercialPedido::COBRADO->value,
        ]);
        $this->assertDatabaseHas('evento_auditorias', [
            'entidad_id' => $pedido->getKey(),
            'tipo_evento' => 'pedido_enviado_cocina',
        ]);
        $printJob = $tanda->fresh()->trabajosImpresion()->first();
        $this->assertNotNull($printJob);
    }

    public function test_charge_and_send_rejects_second_attempt_without_duplicating(): void
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier);
        $service->addProduct($pedido, $this->product, $this->cashier);

        app(CobroService::class)->chargeAndSend($pedido, MetodoPago::TARJETA, null, $this->cashier, ['aprobada' => true, 'referencia' => 'TEST-REF']);

        try {
            app(CobroService::class)->chargeAndSend($pedido, MetodoPago::TARJETA, null, $this->cashier, ['aprobada' => true, 'referencia' => 'TEST-REF']);
            $this->fail('El segundo cobro debía ser rechazado.');
        } catch (\Illuminate\Validation\ValidationException) {
            // El pedido cobrado no puede volver a cobrarse.
        }

        $this->assertDatabaseCount('pagos', 1);
        $this->assertDatabaseCount('tandas_pedido', 1);
    }

    public function test_pending_device_order_generates_no_payment_and_no_comanda(): void
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier, null, OrigenPedido::DISPOSITIVO);
        $service->addProduct($pedido, $this->product, $this->cashier);
        $service->sendToCashRegister($pedido, $this->cashier);

        $this->assertDatabaseCount('pagos', 0);
        $this->assertDatabaseCount('tandas_pedido', 0);
        $this->assertDatabaseCount('trabajo_impresion', 0);
        $this->assertSame(EstadoComercialPedido::PENDIENTE_COBRO, $pedido->fresh()->estado_comercial);
    }

    public function test_table_can_be_assigned_after_order_starts(): void
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier);
        $this->assertNull($pedido->mesa_id);

        $assigned = $service->assignTable($pedido, $this->table, $this->cashier);

        $this->assertSame($this->table->getKey(), $assigned->mesa_id);
        $this->assertSame(TipoPedido::MESA, $assigned->tipo_pedido);
        $this->assertDatabaseHas('mesas', [
            'id' => $this->table->getKey(),
            'estado' => EstadoMesa::OCUPADA->value,
        ]);
        $this->assertDatabaseHas('evento_auditorias', [
            'entidad_id' => $pedido->getKey(),
            'tipo_evento' => 'mesa_asignada',
        ]);
    }

    public function test_assigning_an_occupied_table_is_rejected(): void
    {
        $service = app(PedidoService::class);
        $first = $service->startOrder(TipoPedido::MESA, $this->cashier, $this->table->getKey());
        $second = $service->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier);

        try {
            $service->assignTable($second, $this->table, $this->cashier);
            $this->fail('No debía permitirse ocupar una mesa con cuenta abierta.');
        } catch (\Illuminate\Validation\ValidationException) {
            // La mesa sigue ocupada por la primera cuenta.
        }

        $this->assertSame($first->getKey(), $this->table->fresh()->pedidos()->latest('id')->first()->getKey());
    }

    public function test_ready_batch_appears_on_delivery_board(): void
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier);
        $service->addProduct($pedido, $this->product, $this->cashier);
        [, $tanda] = app(CobroService::class)->chargeAndSend($pedido, MetodoPago::TARJETA, null, $this->cashier, ['aprobada' => true, 'referencia' => 'TEST-REF']);

        app(KitchenService::class)->transition($tanda, EstadoCocina::EN_PREPARACION, $this->cashier);
        app(KitchenService::class)->transition($tanda, EstadoCocina::LISTA, $this->cashier);

        $page = app(\App\Filament\Pages\Pos\EntregaDisplay::class);
        $this->assertTrue($page->readyBatches->contains('id', $tanda->getKey()));
        $this->assertSame('Para llevar · Mostrador', $page->locationLabel($tanda->fresh(['pedido'])));

        $this->actingAs($this->cashier)
            ->get(\App\Filament\Pages\Pos\EntregaDisplay::getUrl())
            ->assertSuccessful();
    }

    public function test_table_is_freed_after_delivery_of_last_batch(): void
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::MESA, $this->cashier, $this->table->getKey());
        $service->addProduct($pedido, $this->product, $this->cashier);
        [, $tanda] = app(CobroService::class)->chargeAndSend($pedido, MetodoPago::TARJETA, null, $this->cashier, ['aprobada' => true, 'referencia' => 'TEST-REF']);

        $this->assertDatabaseHas('mesas', [
            'id' => $this->table->getKey(),
            'estado' => EstadoMesa::OCUPADA->value,
        ]);

        app(KitchenService::class)->transition($tanda, EstadoCocina::EN_PREPARACION, $this->cashier);
        app(KitchenService::class)->transition($tanda, EstadoCocina::LISTA, $this->cashier);
        app(KitchenService::class)->transition($tanda, EstadoCocina::ENTREGADA, $this->cashier);

        $this->assertDatabaseHas('pedidos', [
            'id' => $pedido->getKey(),
            'estado_comercial' => EstadoComercialPedido::CERRADO->value,
        ]);
        $this->assertDatabaseHas('mesas', [
            'id' => $this->table->getKey(),
            'estado' => EstadoMesa::LIBRE->value,
        ]);
    }

    public function test_order_can_be_cancelled_with_permission_and_frees_the_table(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::MESA, $admin, $this->table->getKey());
        $service->addProduct($pedido, $this->product, $admin);

        $cancelled = app(PedidoService::class)->cancelOrder($pedido, $admin);

        $this->assertSame(EstadoComercialPedido::CANCELADO, $cancelled->estado_comercial);
        $this->assertDatabaseHas('mesas', [
            'id' => $this->table->getKey(),
            'estado' => EstadoMesa::LIBRE->value,
        ]);
        $this->assertDatabaseHas('evento_auditorias', [
            'entidad_id' => $pedido->getKey(),
            'tipo_evento' => 'pedido_cancelado',
        ]);
    }

    public function test_cancel_order_requires_permission(): void
    {
        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::PARA_LLEVAR, $this->cashier);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        app(PedidoService::class)->cancelOrder($pedido, $this->cashier);
    }

    public function test_cancelled_order_cannot_be_charged(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        $service = app(PedidoService::class);
        $pedido = $service->startOrder(TipoPedido::PARA_LLEVAR, $admin);
        $service->addProduct($pedido, $this->product, $admin);
        $service->cancelOrder($pedido, $admin);

        try {
            app(CobroService::class)->chargeAndSend($pedido, MetodoPago::TARJETA, null, $admin, ['aprobada' => true, 'referencia' => 'TEST-REF']);
            $this->fail('Un pedido cancelado no puede cobrarse.');
        } catch (\Illuminate\Validation\ValidationException) {
            // Rechazado: el estado CANCELADO no es cobrable.
        }

        $this->assertDatabaseCount('pagos', 0);
        $this->assertDatabaseCount('tandas_pedido', 0);
    }
}
