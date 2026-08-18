<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trabajo_impresion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('impresora_id')->nullable()->constrained('impresoras')->restrictOnDelete();
            $table->foreignId('tanda_id')->nullable()->constrained('tandas_pedido')->cascadeOnDelete(); // set si es comanda
            $table->foreignId('pedido_id')->nullable()->constrained('pedidos')->cascadeOnDelete(); // set si es ticket
            $table->enum('estado', ['PENDIENTE', 'IMPRESO', 'ERROR'])->default('PENDIENTE');
            $table->text('contenido');
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trabajo_impresion');
    }
};
