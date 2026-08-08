<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_pedido_notas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detalle_pedido_id')->constrained('detalles_pedido')->cascadeOnDelete();
            $table->foreignId('nota_cocina_id')->constrained('notas_cocina')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['detalle_pedido_id', 'nota_cocina_id'], 'uk_detalle_nota');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_pedido_notas');
    }
};
