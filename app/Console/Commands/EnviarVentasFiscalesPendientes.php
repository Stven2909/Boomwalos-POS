<?php

namespace App\Console\Commands;

use App\Application\Fiscal\FiscalOutboxService;
use Illuminate\Console\Command;

class EnviarVentasFiscalesPendientes extends Command
{
    protected $signature = 'pos:enviar-fiscales-pendientes';

    protected $description = 'Reintenta el envío de ventas fiscales pendientes (diferidas y en contingencia).';

    public function handle(FiscalOutboxService $outbox): int
    {
        $procesadas = $outbox->enviarPendientes();

        $this->info("Ventas fiscales procesadas: {$procesadas}");

        return self::SUCCESS;
    }
}
