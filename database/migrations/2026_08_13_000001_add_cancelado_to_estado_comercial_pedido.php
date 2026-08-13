<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->enum('estado_comercial', ['ABIERTO', 'PENDIENTE_COBRO', 'COBRADO', 'CERRADO', 'CANCELADO'])
                ->default('ABIERTO')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->enum('estado_comercial', ['ABIERTO', 'PENDIENTE_COBRO', 'COBRADO', 'CERRADO'])
                ->default('ABIERTO')
                ->change();
        });
    }
};
