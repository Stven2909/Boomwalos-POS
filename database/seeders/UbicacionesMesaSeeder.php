<?php

namespace Database\Seeders;

use App\Enums\FlujoUbicacionMesa;
use App\Models\Establecimiento;
use App\Models\Mesa;
use App\Models\UbicacionMesa;
use Illuminate\Database\Seeder;

/**
 * Siembra los datos iniciales de ubicaciones/mesas para la
 * migración del sistema de zonas estáticas a ubicaciones dinámicas.
 *
 * Planes de Renderos:
 *   - Crea 5 ubicaciones: Área Blanca, Área Azul, Área Roja, Área Verde y Llevar.
 *   - "Llevar" se marca con flujo LLEVAR (tablero de pedidos para llevar).
 *
 * Los Boomwalos:
 *   - Soft-delete de sus mesas existentes (conserva el historial de pedidos).
 *
 * Las sucursales se resuelven por nombre para no depender de IDs locales.
 */
class UbicacionesMesaSeeder extends Seeder
{
    private const SUCURSAL_PLANES = 'Los Boomwalos Planes de Renderos';

    private const SUCURSAL_BOOMWALOS = 'Los Boomwalos';

    public function run(): void
    {
        $planes = Establecimiento::query()->where('nombre', self::SUCURSAL_PLANES)->first();

        if (! $planes) {
            $this->command?->warn('No se encontró la sucursal "'.self::SUCURSAL_PLANES.'". No se crearon ubicaciones.');
        } else {
            $ubicaciones = [
                ['nombre' => 'Área Blanca', 'flujo' => FlujoUbicacionMesa::MESAS, 'orden' => 1],
                ['nombre' => 'Área Azul', 'flujo' => FlujoUbicacionMesa::MESAS, 'orden' => 2],
                ['nombre' => 'Área Roja', 'flujo' => FlujoUbicacionMesa::MESAS, 'orden' => 3],
                ['nombre' => 'Área Verde', 'flujo' => FlujoUbicacionMesa::MESAS, 'orden' => 4],
                ['nombre' => 'Llevar', 'flujo' => FlujoUbicacionMesa::LLEVAR, 'orden' => 5],
            ];

            foreach ($ubicaciones as $ubicacion) {
                UbicacionMesa::updateOrCreate(
                    [
                        'establecimiento_id' => $planes->getKey(),
                        'nombre' => $ubicacion['nombre'],
                    ],
                    [
                        'flujo' => $ubicacion['flujo'],
                        'orden' => $ubicacion['orden'],
                        'activa' => true,
                    ],
                );
            }

            $this->command?->info('Ubicaciones de '.$planes->nombre.' creadas.');
        }

        $boomwalos = Establecimiento::query()->where('nombre', self::SUCURSAL_BOOMWALOS)->first();

        if (! $boomwalos) {
            $this->command?->warn('No se encontró la sucursal "'.self::SUCURSAL_BOOMWALOS.'". No se tocaron mesas.');

            return;
        }

        $count = Mesa::withTrashed()
            ->where('establecimiento_id', $boomwalos->getKey())
            ->whereNull('deleted_at')
            ->count();

        Mesa::where('establecimiento_id', $boomwalos->getKey())->delete();

        $this->command?->info("Soft-delete aplicado a {$count} mesa(s) de {$boomwalos->nombre}.");
    }
}
