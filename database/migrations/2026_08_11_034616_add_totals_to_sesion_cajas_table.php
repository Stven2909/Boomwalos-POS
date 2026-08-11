<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sesion_cajas', function (Blueprint $table) {
            $table->decimal('total_efectivo', 10, 2)->nullable()->after('monto_inicial');
            $table->decimal('total_tarjeta', 10, 2)->nullable()->after('total_efectivo');
            $table->decimal('total_ventas', 10, 2)->nullable()->after('total_tarjeta');
        });
    }

    public function down(): void
    {
        Schema::table('sesion_cajas', function (Blueprint $table) {
            $table->dropColumn(['total_efectivo', 'total_tarjeta', 'total_ventas']);
        });
    }
};
