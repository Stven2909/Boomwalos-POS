<?php

use App\Enums\DisponibilidadProducto;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mesas', function (Blueprint $table): void {
            $table->boolean('activa')->default(true)->after('estado');
            $table->dropUnique('mesas_numero_unique');
            $table->unique(['establecimiento_id', 'numero'], 'mesas_establecimiento_numero_unique');
        });

        Schema::table('categorias', function (Blueprint $table): void {
            $table->boolean('activa')->default(true)->after('descripcion');
        });

        Schema::table('combos', function (Blueprint $table): void {
            $table->string('imagen_url', 255)->nullable()->after('precio_fijo');
            $table->string('disponibilidad', 40)
                ->default(DisponibilidadProducto::DISPONIBLE->value)
                ->after('imagen_url');
        });
    }

    public function down(): void
    {
        Schema::table('combos', function (Blueprint $table): void {
            $table->dropColumn(['imagen_url', 'disponibilidad']);
        });

        Schema::table('categorias', function (Blueprint $table): void {
            $table->dropColumn('activa');
        });

        Schema::table('mesas', function (Blueprint $table): void {
            $table->dropUnique('mesas_establecimiento_numero_unique');
            $table->unique('numero', 'mesas_numero_unique');
            $table->dropColumn('activa');
        });
    }
};
