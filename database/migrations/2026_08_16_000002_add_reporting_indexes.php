<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('detalles_pedido', function ($table) {
            $table->index('estado_linea', 'idx_detalles_pedido_estado_linea');
        });

        Schema::table('pedidos', function ($table) {
            $table->index('created_at', 'idx_pedidos_created_at');
            $table->index(['created_at', 'establecimiento_id'], 'idx_pedidos_created_establecimiento');
        });

        Schema::table('pagos', function ($table) {
            $table->index('created_at', 'idx_pagos_created_at');
            $table->index(['sesion_caja_id', 'metodo_pago'], 'idx_pagos_sesion_metodo');
        });

        Schema::table('sesion_cajas', function ($table) {
            $table->index(['establecimiento_id', 'fecha_cierre'], 'idx_sesiones_establecimiento_cierre');
            $table->index(['establecimiento_id', 'fecha_apertura'], 'idx_sesiones_establecimiento_apertura');
        });

        Schema::table('evento_auditorias', function ($table) {
            $table->index(['created_at', 'tipo_evento'], 'idx_auditoria_created_tipo');
        });
    }

    public function down(): void
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('detalles_pedido', function ($table) {
            $table->dropIndex('idx_detalles_pedido_estado_linea');
        });

        Schema::table('pedidos', function ($table) {
            $table->dropIndex('idx_pedidos_created_at');
            $table->dropIndex('idx_pedidos_created_establecimiento');
        });

        Schema::table('pagos', function ($table) {
            $table->dropIndex('idx_pagos_created_at');
            $table->dropIndex('idx_pagos_sesion_metodo');
        });

        Schema::table('sesion_cajas', function ($table) {
            $table->dropIndex('idx_sesiones_establecimiento_cierre');
            $table->dropIndex('idx_sesiones_establecimiento_apertura');
        });

        Schema::table('evento_auditorias', function ($table) {
            $table->dropIndex('idx_auditoria_created_tipo');
        });
    }
};
