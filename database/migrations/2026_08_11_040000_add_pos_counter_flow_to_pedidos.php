<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->enum('origen_pedido', ['CAJA', 'DISPOSITIVO'])->default('CAJA')->after('usuario_id');
            $table->unsignedInteger('codigo_corto')->nullable()->after('origen_pedido');
            $table->date('fecha_codigo')->nullable()->after('codigo_corto');
            $table->enum('estado_comercial', ['ABIERTO', 'PENDIENTE_COBRO', 'COBRADO', 'CERRADO'])
                ->default('ABIERTO')
                ->change();

            $table->index(['establecimiento_id', 'fecha_codigo', 'codigo_corto']);
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropIndex(['establecimiento_id', 'fecha_codigo', 'codigo_corto']);
            $table->dropColumn(['origen_pedido', 'codigo_corto', 'fecha_codigo']);
            $table->enum('estado_comercial', ['ABIERTO', 'COBRADO', 'CERRADO'])
                ->default('ABIERTO')
                ->change();
        });
    }
};
