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
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->string('numero_seguimiento', 20)->unique();
            $table->enum('tipo_pedido', ['MESA', 'PARA_LLEVAR']);
            $table->foreignId('mesa_id')->nullable()->constrained('mesas')->restrictOnDelete();
            $table->foreignId('establecimiento_id')->constrained('establecimientos')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->enum('estado_comercial', ['ABIERTO', 'COBRADO', 'CERRADO'])->default('ABIERTO');
            $table->timestamps();

            $table->index(['establecimiento_id', 'estado_comercial']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};
