<?php

namespace Database\Seeders;

use App\Models\Mesa;
use App\Models\UbicacionMesa;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Siembra los datos iniciales de ubicaciones/mesas para la
 * migración del sistema de zonas estáticas a ubicaciones dinámicas.
 *
 * Planes de Renderos (establecimiento_id = 3):
 *   - Crea 5 ubicaciones: Área Blanca, Área Azul, Área Roja, Área Verde, Llevar.
 *
 * Los Boomwalos (establecimiento_id = 1):
 *   - Soft-delete de las 16 mesas existentes.
 */
class UbicacionesMesaSeeder extends Seeder
{
    public function run(): void
    {
        // --- Planes de Renderos: 5 ubicaciones ---
        $planesId = 3;

        $ubicaciones = [
            ['nombre' => 'Área Blanca', 'orden' => 1],
            ['nombre' => 'Área Azul',   'orden' => 2],
            ['nombre' => 'Área Roja',   'orden' => 3],
            ['nombre' => 'Área Verde',  'orden' => 4],
            ['nombre' => 'Llevar',      'orden' => 5],
        ];

        foreach ($ubicaciones as $ub) {
            UbicacionMesa::updateOrCreate(
                [
                    'establecimiento_id' => $planesId,
                    'nombre'             => $ub['nombre'],
                ],
                [
                    'orden'  => $ub['orden'],
                    'activa' => true,
                ],
            );
        }

        $this->command->info('Ubicaciones de Planes de Renderos creadas.');

        // --- Los Boomwalos (id 1): soft-delete de las 16 mesas ---
        $boomwalosId = 1;

        $count = Mesa::withTrashed()
            ->where('establecimiento_id', $boomwalosId)
            ->whereNull('deleted_at')
            ->count();

        Mesa::where('establecimiento_id', $boomwalosId)->delete();

        $this->command->info("Soft-delete aplicado a {$count} mesa(s) de Los Boomwalos (establecimiento #{$boomwalosId}).");
    }
}
