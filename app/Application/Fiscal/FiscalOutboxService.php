<?php

namespace App\Application\Fiscal;

use App\Enums\EstadoColaVentaFiscal;
use App\Enums\EstadoDocumentoFiscal;
use App\Enums\EstadoVentaFiscal;
use App\Jobs\EnviarVentasFiscalesJob;
use App\Models\ColaVentaFiscal;
use App\Models\ConfiguracionFiscal;
use App\Models\DocumentoFiscal;
use App\Models\Establecimiento;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\VentaFiscalPos;
use Illuminate\Support\Facades\DB;
use Throwable;

class FiscalOutboxService
{
    public function registrarVenta(Pedido $pedido, Pago $pago, ConfiguracionFiscal $config): VentaFiscalPos
    {
        $establecimiento = $config->establecimiento;
        $clave = $this->claveReintento($establecimiento, $pedido, $pago);

        $venta = VentaFiscalPos::create([
            'establecimiento_id' => $establecimiento->getKey(),
            'pedido_id' => $pedido->getKey(),
            'pago_id' => $pago->getKey(),
            'referencia' => $pedido->numero_seguimiento,
            'monto_total' => $this->neto($pago),
            'metodo_pago' => $pago->metodo_pago->value,
            'receptor' => $this->receptorPendiente($pedido),
            'estado' => EstadoVentaFiscal::NO->value,
        ]);

        $venta->cola()->create([
            'clave_reintento' => $clave,
            'payload_envio' => [
                'clave_reintento' => $clave,
                'referencia' => $venta->referencia,
                'fecha_emision' => $venta->created_at?->toIso8601String() ?? now()->toIso8601String(),
                'monto_total' => number_format((float) $venta->monto_total, 2, '.', ''),
                'metodo_pago' => $venta->metodo_pago,
                'receptor' => $venta->receptor,
            ],
            'estado' => EstadoColaVentaFiscal::PENDIENTE->value,
        ]);

        dispatch(new EnviarVentasFiscalesJob($venta->getKey()));

        return $venta;
    }

    public function enviarPendientes(?int $ventaFiscalPosId = null): int
    {
        $consulta = ColaVentaFiscal::query()
            ->with(['ventaFiscalPos.establecimiento.configuracionFiscal'])
            ->where('estado', EstadoColaVentaFiscal::PENDIENTE->value)
            ->orderBy('id');

        if ($ventaFiscalPosId !== null) {
            $consulta->where('venta_fiscal_pos_id', $ventaFiscalPosId);
        }

        $procesadas = 0;

        $consulta->get()->each(function (ColaVentaFiscal $cola) use (&$procesadas): void {
            if ($this->enviar($cola)) {
                $procesadas++;
            }
        });

        return $procesadas;
    }

    public function reintentar(ColaVentaFiscal $cola): void
    {
        $cola->update([
            'estado' => EstadoColaVentaFiscal::PENDIENTE->value,
            'ultimo_error' => null,
        ]);

        $cola->ventaFiscalPos()->update(['estado' => EstadoVentaFiscal::NO->value]);

        dispatch(new EnviarVentasFiscalesJob($cola->venta_fiscal_pos_id));
    }

    private function enviar(ColaVentaFiscal $cola): bool
    {
        $venta = $cola->ventaFiscalPos;
        $config = $venta->establecimiento->configuracionFiscal;

        if (! $config?->fiscal_habilitada) {
            return false;
        }

        $cola->increment('intentos');

        try {
            $respuesta = app(FiscalClient::class)->enviarVenta($config, $cola->payload_envio);

            DB::transaction(function () use ($cola, $venta, $respuesta): void {
                $venta->update([
                    'fiscal_sale_id' => $respuesta['fiscal_sale_id'] ?? $venta->fiscal_sale_id,
                    'estado' => EstadoVentaFiscal::SINCRONIZADO->value,
                    'receptor' => null,
                    'sincronizado_at' => now(),
                ]);

                $cola->update([
                    'estado' => EstadoColaVentaFiscal::ENVIADO->value,
                    'ultimo_error' => null,
                ]);

                DocumentoFiscal::query()
                    ->where('pedido_id', $venta->pedido_id)
                    ->where('estado', EstadoDocumentoFiscal::PENDIENTE->value)
                    ->update(['datos_solicitante' => null]);
            });

            return true;
        } catch (Throwable $exception) {
            $venta->update(['estado' => EstadoVentaFiscal::ENVIO_FALLIDO->value]);
            $cola->update([
                'estado' => EstadoColaVentaFiscal::FALLIDO->value,
                'ultimo_error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function receptorPendiente(Pedido $pedido): ?array
    {
        return DocumentoFiscal::query()
            ->where('pedido_id', $pedido->getKey())
            ->where('estado', EstadoDocumentoFiscal::PENDIENTE->value)
            ->value('datos_solicitante');
    }

    private function claveReintento(Establecimiento $establecimiento, Pedido $pedido, Pago $pago): string
    {
        return 'v-' . $establecimiento->getKey() . '-' . $pedido->getKey() . '-' . $pago->getKey();
    }

    private function neto(Pago $pago): string
    {
        return bcsub((string) $pago->monto_recibido, (string) $pago->cambio_devuelto, 2);
    }
}
