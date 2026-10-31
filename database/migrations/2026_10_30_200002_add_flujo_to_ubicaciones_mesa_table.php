<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ubicaciones_mesa', function (Blueprint $table): void {
            $table->string('flujo', 20)
                ->default('MESAS')
                ->after('nombre')
                ->index();
        });

        // Las ubicaciones llamadas "Llevar" pasan a ser tablero de pedidos para llevar.
        DB::table('ubicaciones_mesa')
            ->whereRaw('LOWER(nombre) = ?', ['llevar'])
            ->update(['flujo' => 'LLEVAR']);
    }

    public function down(): void
    {
        Schema::table('ubicaciones_mesa', function (Blueprint $table): void {
            $table->dropColumn('flujo');
        });
    }
};
