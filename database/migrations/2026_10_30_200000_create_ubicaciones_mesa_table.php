<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ubicaciones_mesa', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establecimiento_id')->constrained('establecimientos')->cascadeOnDelete();
            $table->string('nombre', 60);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->unique(['establecimiento_id', 'nombre'], 'ubicaciones_establecimiento_nombre_unique');
        });

        // Backfill: crear ubicaciones a partir de las zonas ya existentes en mesas.
        $zonas = DB::table('mesas')
            ->select('establecimiento_id', 'zona')
            ->distinct()
            ->orderBy('establecimiento_id')
            ->orderBy('zona')
            ->get();

        $porEstablecimiento = [];
        foreach ($zonas as $row) {
            $porEstablecimiento[$row->establecimiento_id][] = $row->zona;
        }

        foreach ($porEstablecimiento as $estId => $nombres) {
            foreach (array_values($nombres) as $i => $nombre) {
                DB::table('ubicaciones_mesa')->insert([
                    'establecimiento_id' => $estId,
                    'nombre'             => $nombre,
                    'orden'              => $i + 1,
                    'activa'             => true,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ubicaciones_mesa');
    }
};
