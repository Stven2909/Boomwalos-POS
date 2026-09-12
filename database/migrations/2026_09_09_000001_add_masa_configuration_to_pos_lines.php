<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table): void {
            $table->boolean('requiere_masa')->default(false)->after('disponibilidad');
        });

        Schema::table('detalles_pedido', function (Blueprint $table): void {
            $table->json('configuracion_producto')->nullable()->after('seleccion_combo');
        });
    }

    public function down(): void
    {
        Schema::table('detalles_pedido', function (Blueprint $table): void {
            $table->dropColumn('configuracion_producto');
        });

        Schema::table('productos', function (Blueprint $table): void {
            $table->dropColumn('requiere_masa');
        });
    }
};