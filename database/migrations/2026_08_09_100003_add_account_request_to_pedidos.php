<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table): void {
            $table->timestamp('cuenta_solicitada_at')->nullable()->after('estado_comercial');
            $table->foreignId('cuenta_solicitada_por_id')
                ->nullable()
                ->after('cuenta_solicitada_at')
                ->constrained('usuarios')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table): void {
            $table->dropForeign(['cuenta_solicitada_por_id']);
            $table->dropColumn(['cuenta_solicitada_at', 'cuenta_solicitada_por_id']);
        });
    }
};
