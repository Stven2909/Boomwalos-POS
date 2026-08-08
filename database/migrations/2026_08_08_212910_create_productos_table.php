<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_id')->constrained('categorias')->restrictOnDelete();
            $table->string('nombre', 100);
            $table->decimal('precio', 10, 2);
            $table->string('imagen_url', 255)->nullable();
            $table->enum('disponibilidad', ['DISPONIBLE', 'AGOTADO', 'TEMPORALMENTE_NO_DISPONIBLE'])
                ->default('DISPONIBLE');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
