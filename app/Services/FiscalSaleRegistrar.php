<?php

namespace App\Services;

use App\Application\Fiscal\FiscalOutboxService;
use App\Models\ConfiguracionFiscal;
use App\Models\Pago;
use App\Models\VentaFiscalPos;

class FiscalSaleRegistrar
{
    public function __construct(private readonly FiscalOutboxService $outbox) {}

    public function register(Pago $pago): void
    {
        $configuracion = ConfiguracionFiscal::query()
            ->where('establecimiento_id', $pago->pedido->establecimiento_id)
            ->where('fiscal_habilitada', true)
            ->first();

        if (! $configuracion || VentaFiscalPos::query()->where('pago_id', $pago->getKey())->exists()) {
            return;
        }

        $this->outbox->registrarVenta($pago->pedido, $pago, $configuracion);
    }
}
