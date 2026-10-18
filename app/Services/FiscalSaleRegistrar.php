<?php

namespace App\Services;

use App\Application\Fiscal\FiscalOutboxService;
use App\Models\ConfiguracionFiscal;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\VentaFiscalPos;
use Illuminate\Support\Str;

class FiscalSaleRegistrar
{
    public function __construct(private readonly FiscalOutboxService $outbox) {}

    /**
     * Resuelve la identidad fiscal de la venta antes de imprimir el ticket:
     * sondea la conectividad y, si no hay enlace, devuelve el código de
     * generación y la marca de contingencia (modelo diferido).
     */
    public function preparar(Pedido $pedido): array
    {
        $configuracion = $this->configuracionPara($pedido->establecimiento_id);

        if (! $configuracion) {
            return ['activa' => false, 'contingencia' => false];
        }

        $contingencia = ! $this->outbox->hayConexion();

        return [
            'activa' => true,
            'contingencia' => $contingencia,
            'codigo_generacion' => $contingencia ? strtoupper((string) Str::uuid()) : null,
            'fecha_emision' => now()->toIso8601String(),
        ];
    }

    public function register(Pago $pago, array $identidad = []): void
    {
        $configuracion = $this->configuracionPara($pago->pedido->establecimiento_id);

        if (! $configuracion || VentaFiscalPos::query()->where('pago_id', $pago->getKey())->exists()) {
            return;
        }

        $this->outbox->registrarVenta($pago->pedido, $pago, $configuracion, $identidad);
    }

    private function configuracionPara(?int $establecimientoId): ?ConfiguracionFiscal
    {
        if ($establecimientoId === null) {
            return null;
        }

        return ConfiguracionFiscal::query()
            ->where('establecimiento_id', $establecimientoId)
            ->where('fiscal_habilitada', true)
            ->first();
    }
}
