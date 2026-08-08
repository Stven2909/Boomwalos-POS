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
        Schema::create('evento_auditorias', function (Blueprint $table) {
            $table->id();
            // Polimórfico: equivalente a $table->morphs('entidad'), escrito
            // explícito para que coincida 1:1 con los nombres del ERD.
            $table->string('entidad_tipo', 50);
            $table->unsignedBigInteger('entidad_id');
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->string('tipo_evento', 100); // ej. "pedido_creado", "pedido_cobrado"
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entidad_tipo', 'entidad_id']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evento_auditorias');
    }
};
