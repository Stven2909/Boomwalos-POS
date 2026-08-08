<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opciones_combo_productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opcion_combo_id')->constrained('opciones_combo')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['opcion_combo_id', 'producto_id'], 'uk_opcion_producto');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opciones_combo_productos');
    }
};
