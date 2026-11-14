<?php

namespace App\Console\Commands;

use App\Services\Portal\PortalReconciliador;
use Illuminate\Console\Command;

class PortalReconciliar extends Command
{
    protected $signature = 'portal:reconciliar {--limit=100 : Máximo de órdenes a consultar por ejecución}';

    protected $description = 'Sincroniza desde ApiFact el estado y los identificadores del DTE de las órdenes del portal.';

    public function handle(PortalReconciliador $reconciliador): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $resumen = $reconciliador->reconciliar($limit);

        $this->info(sprintf(
            'Órdenes revisadas: %d · actualizadas: %d · sin cambios: %d · errores: %d',
            $resumen['revisadas'],
            $resumen['actualizadas'],
            $resumen['sin_cambios'],
            $resumen['errores'],
        ));

        return self::SUCCESS;
    }
}
