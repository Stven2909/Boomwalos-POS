<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones_fiscales', function (Blueprint $table): void {
            $table->string('razon_social', 200)->nullable()->after('establecimiento_id');
            $table->string('nit', 30)->nullable()->after('razon_social');
            $table->string('nrc', 30)->nullable()->after('nit');
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_fiscales', function (Blueprint $table): void {
            $table->dropColumn(['razon_social', 'nit', 'nrc']);
        });
    }
};
