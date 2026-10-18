<?php

namespace App\Console\Commands;

use App\Application\Fiscal\FiscalOutboxService;
use Illuminate\Console\Command;

class ReconciliarFiscales extends Command
{
    protected $signature = 'pos:reconciliar-fiscales {--limit=100 : Máximo de ventas a consultar por ejecución}';

    protected $description = 'Reconcilia por consulta el estado fiscal de las ventas diferidas (contingencia).';

    public function handle(FiscalOutboxService $outbox): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $selladas = $outbox->reconciliar($limit);

        $this->info("Ventas selladas reconciliadas: {$selladas}");

        return self::SUCCESS;
    }
}
