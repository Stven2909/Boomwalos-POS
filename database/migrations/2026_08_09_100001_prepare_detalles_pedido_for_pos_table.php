<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalles_pedido', function (Blueprint $table): void {
            $table->foreignId('tanda_id')->nullable()->change();
            $table->enum('estado_linea', ['ACTIVA', 'CANCELADA'])
                ->default('ACTIVA')
                ->after('tanda_id');
            $table->foreignId('cancelada_por_id')
                ->nullable()
                ->after('estado_linea')
                ->constrained('usuarios')
                ->nullOnDelete();
            $table->timestamp('cancelada_at')->nullable()->after('cancelada_por_id');
            $table->string('motivo_cancelacion', 255)->nullable()->after('cancelada_at');
        });
    }

    public function down(): void
    {
        Schema::table('detalles_pedido', function (Blueprint $table): void {
            $table->dropForeign(['cancelada_por_id']);
            $table->dropColumn([
                'estado_linea',
                'cancelada_por_id',
                'cancelada_at',
                'motivo_cancelacion',
            ]);
            $table->foreignId('tanda_id')->nullable(false)->change();
        });
    }
};
