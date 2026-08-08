<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tandas_pedido', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->integer('numero_tanda');
            $table->enum('estado_cocina', ['PENDIENTE', 'EN_PREPARACION', 'LISTA', 'ENTREGADA', 'CANCELADA'])
                ->default('PENDIENTE');
            $table->timestamps();

            $table->unique(['pedido_id', 'numero_tanda'], 'uk_tanda_pedido');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tandas_pedido');
    }
};
