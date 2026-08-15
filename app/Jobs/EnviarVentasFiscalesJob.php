<?php

namespace App\Jobs;

use App\Application\Fiscal\FiscalOutboxService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EnviarVentasFiscalesJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $ventaFiscalPosId) {}

    public function handle(FiscalOutboxService $service): void
    {
        $service->enviarPendientes($this->ventaFiscalPosId);
    }
}
