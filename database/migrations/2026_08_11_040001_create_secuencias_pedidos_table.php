<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secuencias_pedidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_id')->constrained('establecimientos')->cascadeOnDelete();
            $table->date('fecha');
            $table->unsignedInteger('ultimo_valor')->default(0);
            $table->timestamps();

            $table->unique(['establecimiento_id', 'fecha'], 'uk_secuencia_establecimiento_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secuencias_pedidos');
    }
};
