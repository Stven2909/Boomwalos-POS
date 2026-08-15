<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuraciones_fiscales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_id')->constrained('establecimientos')->cascadeOnDelete();
            $table->boolean('fiscal_habilitada')->default(false);
            $table->string('cliente_key')->nullable();
            $table->text('cliente_secret')->nullable();
            $table->unsignedTinyInteger('intentos_maximos')->default(3);
            $table->timestamps();

            $table->unique(['establecimiento_id'], 'uk_configuraciones_fiscales_establecimiento');
        });

        Schema::create('fiscal_sync_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_id')->constrained('establecimientos')->cascadeOnDelete();
            $table->unsignedBigInteger('ultima_secuencia_webhook')->default(0);
            $table->timestamps();

            $table->unique(['establecimiento_id'], 'uk_fiscal_sync_states_establecimiento');
        });

        Schema::create('ventas_fiscales_pos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_id')->constrained('establecimientos');
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->foreignId('pago_id')->nullable()->constrained('pagos')->nullOnDelete();
            $table->string('referencia', 50);
            $table->decimal('monto_total', 12, 2);
            $table->string('metodo_pago', 20);
            $table->json('receptor')->nullable();
            $table->string('fiscal_sale_id')->nullable();
            $table->enum('estado', ['SINCRONIZADO', 'NO', 'ENVIO_FALLIDO'])->default('NO');
            $table->timestamp('sincronizado_at')->nullable();
            $table->timestamps();

            $table->unique(['pago_id'], 'uk_ventas_fiscales_pos_pago');
            $table->index(['establecimiento_id', 'estado'], 'idx_ventas_fiscales_pos_estado');
        });

        Schema::create('cola_ventas_fiscales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_fiscal_pos_id')->constrained('ventas_fiscales_pos')->cascadeOnDelete();
            $table->string('clave_reintento', 100)->unique();
            $table->json('payload_envio');
            $table->enum('estado', ['PENDIENTE', 'ENVIADO', 'FALLIDO'])->default('PENDIENTE');
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->string('ultimo_error')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_events_pos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_id')->nullable()->constrained('establecimientos')->cascadeOnDelete();
            $table->foreignId('venta_fiscal_pos_id')->nullable()->constrained('ventas_fiscales_pos')->nullOnDelete();
            $table->unsignedBigInteger('secuencia');
            $table->string('tipo', 50);
            $table->json('payload');
            $table->enum('estado', ['PENDIENTE', 'PROCESADO', 'RECONCILIADO'])->default('PENDIENTE');
            $table->timestamp('recibido_at');
            $table->timestamps();

            $table->index(['establecimiento_id', 'secuencia'], 'idx_webhook_events_pos_secuencia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events_pos');
        Schema::dropIfExists('cola_ventas_fiscales');
        Schema::dropIfExists('ventas_fiscales_pos');
        Schema::dropIfExists('fiscal_sync_states');
        Schema::dropIfExists('configuraciones_fiscales');
    }
};
