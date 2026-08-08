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
        Schema::create('documento_fiscales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->restrictOnDelete();
            $table->enum('tipo_documento', ['FACTURA', 'CCF']);
            $table->string('numero_control', 50)->nullable();
            $table->string('codigo_generacion', 36)->nullable();
            $table->string('sello_recepcion', 100)->nullable();
            $table->enum('estado', ['PENDIENTE', 'EMITIDO', 'RECHAZADO'])->default('PENDIENTE');
            $table->json('datos_solicitante')->nullable();
            $table->timestamps();

            $table->unique(['pedido_id', 'tipo_documento'], 'uk_documento_fiscal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documento_fiscales');
    }
};
