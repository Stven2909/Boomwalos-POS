<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('establecimiento_usuario', function (Blueprint $table): void {
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->foreignId('establecimiento_id')->constrained('establecimientos')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['usuario_id', 'establecimiento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('establecimiento_usuario');
    }
};
