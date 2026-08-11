<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->foreignId('sesion_caja_id')
                ->nullable()
                ->after('pedido_id')
                ->constrained('sesion_cajas')
                ->restrictOnDelete();

            $table->index('sesion_caja_id');
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropForeign(['sesion_caja_id']);
            $table->dropIndex(['sesion_caja_id']);
            $table->dropColumn('sesion_caja_id');
        });
    }
};
