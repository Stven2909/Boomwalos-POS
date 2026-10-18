<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas_fiscales_pos', function (Blueprint $table): void {
            $table->uuid('codigo_generacion')->nullable()->after('fiscal_sale_id');
            $table->timestamp('fecha_emision')->nullable()->after('codigo_generacion');
        });

        Schema::table('cola_ventas_fiscales', function (Blueprint $table): void {
            $table->boolean('contingencia')->default(false)->after('intentos');
            $table->timestamp('contingencia_desde')->nullable()->after('contingencia');
            $table->timestamp('proximo_intento_at')->nullable()->after('ultimo_error');
        });
    }

    public function down(): void
    {
        Schema::table('cola_ventas_fiscales', function (Blueprint $table): void {
            $table->dropColumn(['contingencia', 'contingencia_desde', 'proximo_intento_at']);
        });

        Schema::table('ventas_fiscales_pos', function (Blueprint $table): void {
            $table->dropColumn(['codigo_generacion', 'fecha_emision']);
        });
    }
};
