<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalles_pedido', function (Blueprint $table) {
            $table->dropForeign(['tanda_id']);
            $table->foreign('tanda_id')->references('id')->on('trabajo_impresion')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('detalles_pedido', function (Blueprint $table) {
            $table->dropForeign(['tanda_id']);
            $table->foreign('tanda_id')->references('id')->on('tandas_pedido')->cascadeOnDelete();
        });
    }
};
