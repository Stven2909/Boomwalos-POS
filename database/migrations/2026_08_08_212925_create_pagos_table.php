<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->restrictOnDelete();
            $table->enum('metodo_pago', ['EFECTIVO', 'TARJETA']);
            $table->decimal('monto_recibido', 10, 2)->nullable();
            $table->decimal('cambio_devuelto', 10, 2)->nullable();
            $table->timestamps();

            // Garantiza a nivel de base de datos que un pedido no pueda
            // cobrarse dos veces (regla de negocio confirmada).
            $table->unique('pedido_id', 'uk_pago_pedido');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
