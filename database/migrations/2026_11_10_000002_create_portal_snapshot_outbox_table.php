<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_snapshot_outbox', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pedido_id')->unique()->constrained('pedidos')->restrictOnDelete();
            $table->foreignId('establecimiento_id')->constrained('establecimientos')->restrictOnDelete();
            $table->string('tracking', 50)->unique();
            $table->json('payload');
            $table->string('estado', 20)->default('PENDIENTE');
            $table->unsignedInteger('intentos')->default(0);
            $table->timestamp('proximo_intento_at')->nullable();
            $table->timestamp('bloqueado_at')->nullable();
            $table->timestamp('sincronizado_at')->nullable();
            $table->text('ultimo_error')->nullable();
            $table->timestamps();

            $table->index(['estado', 'proximo_intento_at'], 'portal_snapshot_estado_intento_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_snapshot_outbox');
    }
};
