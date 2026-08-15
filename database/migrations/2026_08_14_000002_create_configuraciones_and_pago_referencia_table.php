<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuraciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establecimiento_id')->constrained('establecimientos')->cascadeOnDelete();
            $table->string('clave', 100);
            $table->json('valor')->nullable();
            $table->timestamps();

            $table->unique(['establecimiento_id', 'clave'], 'uk_configuraciones_establecimiento_clave');
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->string('referencia_externa', 100)->nullable()->after('cambio_devuelto');
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropColumn('referencia_externa');
        });

        Schema::dropIfExists('configuraciones');
    }
};
