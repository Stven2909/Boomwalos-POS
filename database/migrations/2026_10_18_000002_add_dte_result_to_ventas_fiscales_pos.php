<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas_fiscales_pos', function (Blueprint $table): void {
            $table->string('numero_control', 60)->nullable()->after('codigo_generacion');
            $table->string('sello_recepcion', 100)->nullable()->after('numero_control');
        });
    }

    public function down(): void
    {
        Schema::table('ventas_fiscales_pos', function (Blueprint $table): void {
            $table->dropColumn(['numero_control', 'sello_recepcion']);
        });
    }
};
