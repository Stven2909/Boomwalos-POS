<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalles_pedido', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->foreignId('tanda_id')->constrained('tandas_pedido')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->restrictOnDelete();
            $table->foreignId('combo_id')->nullable()->constrained('combos')->restrictOnDelete();
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 10, 2); // capturado al momento de la venta
            $table->json('seleccion_combo')->nullable(); // sabores/bebida elegidos si combo_id no es null
            $table->timestamps();
        });

        // Requiere MySQL >= 8.0.16. Fuerza producto_id XOR combo_id: uno de los
        // dos, nunca ambos, nunca ninguno.
        DB::statement('
            ALTER TABLE detalles_pedido
            ADD CONSTRAINT chk_producto_o_combo
            CHECK (
                (producto_id IS NOT NULL AND combo_id IS NULL) OR
                (producto_id IS NULL AND combo_id IS NOT NULL)
            )
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('detalles_pedido');
    }
};
