<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones_fiscales', function (Blueprint $table): void {
            $table->string('ambiente', 2)->default('00')->after('nrc');
            $table->string('giro', 250)->nullable()->after('ambiente');
            $table->string('codigo_establecimiento', 10)->nullable()->default('0001')->after('giro');
            $table->string('codigo_punto_venta', 10)->nullable()->default('001')->after('codigo_establecimiento');
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_fiscales', function (Blueprint $table): void {
            $table->dropColumn(['ambiente', 'giro', 'codigo_establecimiento', 'codigo_punto_venta']);
        });
    }
};
