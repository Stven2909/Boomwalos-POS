<?php

namespace App\Application\Fiscal;

use App\Application\Printing\RenderDteTicket;
use App\Contracts\FiscalGatewayInterface;
use App\Enums\EstadoColaVentaFiscal;
use App\Enums\EstadoDocumentoFiscal;
use App\Enums\EstadoImpresion;
use App\Enums\EstadoVentaFiscal;
use App\Enums\TipoImpresora;
use App\Enums\TipoTrabajoImpresion;
use App\Jobs\EnviarVentasFiscalesJob;
use App\Jobs\ProcessPrintJob;
use App\Models\ColaVentaFiscal;
use App\Models\ConfiguracionFiscal;
use App\Models\DocumentoFiscal;
use App\Models\Establecimiento;
use App\Models\Impresora;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\TrabajoImpresion;
use App\Models\VentaFiscalPos;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class FiscalOutboxService
{
    public function __construct(
        private readonly FiscalGatewayInterface $fiscalGateway,
    ) {}

    public function registrarVenta(Pedido $pedido, Pago $pago, ConfiguracionFiscal $config, array $identidad = []): VentaFiscalPos
    {
        $establecimiento = $config->establecimiento;
        $clave = $this->claveReintento($establecimiento, $pedido, $pago);
        $receptor = $this->receptorPendiente($pedido);
        $tipoDte = (! empty($receptor['nrc'])) ? '03' : '01';

        $contingencia = (bool) ($identidad['contingencia'] ?? false);
        $fechaEmision = $identidad['fecha_emision'] ?? now()->toIso8601String();
        $codigoGeneracion = $contingencia
            ? strtoupper((string) ($identidad['codigo_generacion'] ?? Str::uuid()))
            : null;

        $items = [];
        $pedido->loadMissing(['detalles.producto', 'detalles.combo']);
        foreach ($pedido->detalles as $detalle) {
            if ($detalle->estado_linea && $detalle->estado_linea->value === 'CANCELADA') {
                continue;
            }
            $items[] = [
                'nombre' => $detalle->combo?->nombre ?? $detalle->producto?->nombre ?? 'Consumo en Restaurante',
                'cantidad' => (float) ($detalle->cantidad ?? 1),
                'precio_unitario' => (float) ($detalle->precio_unitario ?? 0),
            ];
        }

        if (empty($items)) {
            $items[] = [
                'nombre' => 'Consumo de Alimentos y Bebidas',
                'cantidad' => 1,
                'precio_unitario' => (float) $this->neto($pago),
            ];
        }

        $venta = VentaFiscalPos::create([
            'establecimiento_id' => $establecimiento->getKey(),
            'pedido_id' => $pedido->getKey(),
            'pago_id' => $pago->getKey(),
            'referencia' => $pedido->numero_seguimiento,
            'monto_total' => $this->neto($pago),
            'metodo_pago' => $pago->metodo_pago->value,
            'receptor' => $receptor,
            'codigo_generacion' => $codigoGeneracion,
            'fecha_emision' => $contingencia ? $fechaEmision : null,
            'estado' => EstadoVentaFiscal::NO->value,
        ]);

        $payload = [
            'establecimiento' => (string) ($config->codigo_establecimiento ?: 'M001'),
            'puntoVenta' => (string) ($config->codigo_punto_venta ?: 'P001'),
            'tipoDte' => $tipoDte,
            'propina' => 0.0,
            'condicionOperacion' => 1,
            'metodoPago' => match ($venta->metodo_pago) {
                'EFECTIVO' => '01',
                'TARJETA' => '02',
                'TRANSFERENCIA' => '04',
                default => '01',
            },
            'cliente' => $receptor,
            'items' => $items,
            'clave_reintento' => $clave,
            'referencia' => $venta->referencia,
            'fecha_emision' => $fechaEmision,
            'monto_total' => number_format((float) $venta->monto_total, 2, '.', ''),
            'metodo_pago' => $venta->metodo_pago,
            'receptor' => $venta->receptor,
        ];

        if ($contingencia) {
            $payload += [
                'codigo_generacion' => $codigoGeneracion,
                'tipoModelo' => 2,
                'tipoOperacion' => 2,
                'tipoContingencia' => 3,
                'motivoContin' => null,
            ];
        }

        $venta->cola()->create([
            'clave_reintento' => $clave,
            'payload_envio' => $payload,
            'contingencia' => $contingencia,
            'contingencia_desde' => $contingencia ? $fechaEmision : null,
            'estado' => EstadoColaVentaFiscal::PENDIENTE->value,
        ]);

        dispatch(new EnviarVentasFiscalesJob($venta->getKey()));

        return $venta;
    }

    /**
     * Sonda de conectividad hacia la API fiscal previa a la venta. Un fallo
     * (DNS/socket) marca la venta como contingencia y la difiere.
     */
    public function hayConexion(): bool
    {
        $parts = parse_url((string) config('fiscal.url'));

        if (! is_array($parts) || empty($parts['host'])) {
            return false;
        }

        $scheme = $parts['scheme'] ?? 'http';
        $host = (string) $parts['host'];
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $timeout = (int) config('fiscal.probe_timeout', 3);

        $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);

        if (is_resource($connection)) {
            fclose($connection);

            return true;
        }

        return false;
    }

    public function enviarPendientes(?int $ventaFiscalPosId = null): int
    {
        $consulta = ColaVentaFiscal::query()
            ->with(['ventaFiscalPos.establecimiento.configuracionFiscal'])
            ->where('estado', EstadoColaVentaFiscal::PENDIENTE->value)
            ->where(function ($query): void {
                $query->whereNull('proximo_intento_at')
                    ->orWhere('proximo_intento_at', '<=', now());
            })
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

    /**
     * Reconciliación por pull: para las ventas diferidas sin webhook (el
     * kiosko local no es alcanzable desde la nube), consulta el estado fiscal
     * del DTE por su Código de Generación. Cuando Hacienda lo acepta, marca la
     * venta como sincronizada y encola la reimpresión del DTE final.
     */
    public function reconciliar(int $limit = 100): int
    {
        $ventas = VentaFiscalPos::query()
            ->with(['establecimiento.configuracionFiscal', 'cola'])
            ->whereNotNull('codigo_generacion')
            ->where(function ($query): void {
                $query->where('estado', '!=', EstadoVentaFiscal::SINCRONIZADO->value)
                    ->orWhereNull('sello_recepcion');
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $sellados = 0;

        foreach ($ventas as $venta) {
            $config = $venta->establecimiento?->configuracionFiscal;

            if (! $config?->fiscal_habilitada) {
                continue;
            }

            try {
                $respuesta = $this->fiscalGateway->consultarVenta($config, (string) $venta->codigo_generacion);
            } catch (Throwable) {
                continue; // sin enlace o DTE aún no disponible: se reintenta en la próxima pasada
            }

            if (strtoupper((string) ($respuesta['estadoFiscal'] ?? '')) !== 'SEALED') {
                continue;
            }

            DB::transaction(function () use ($venta, $respuesta): void {
                $venta->update([
                    'estado' => EstadoVentaFiscal::SINCRONIZADO->value,
                    'fiscal_sale_id' => $respuesta['codigoGeneracion'] ?? $venta->fiscal_sale_id,
                    'numero_control' => $respuesta['numeroControl'] ?? $venta->numero_control,
                    'sello_recepcion' => $respuesta['selloRecepcion'] ?? null,
                    'sincronizado_at' => now(),
                    'receptor' => null,
                ]);

                $venta->cola()->update([
                    'estado' => EstadoColaVentaFiscal::ENVIADO->value,
                    'ultimo_error' => null,
                    'proximo_intento_at' => null,
                ]);
            });

            $this->encolarReimpresionDteFinal($venta->fresh());
            $sellados++;
        }

        return $sellados;
    }

    private function encolarReimpresionDteFinal(VentaFiscalPos $venta): void
    {
        $impresora = Impresora::buscar(TipoImpresora::TICKET, $venta->establecimiento_id);

        if (! $impresora) {
            return;
        }

        $trabajo = TrabajoImpresion::firstOrCreate(
            ['original_uid' => hash('sha256', $venta->getKey().'|DTE_FINAL')],
            [
                'impresora_id' => $impresora->getKey(),
                'pedido_id' => $venta->pedido_id,
                'tipo_trabajo' => TipoTrabajoImpresion::TICKET,
                'es_reimpresion' => true,
                'motivo_reimpresion' => 'DTE final (contingencia sincronizada)',
                'estado' => EstadoImpresion::PENDIENTE,
                'contenido' => app(RenderDteTicket::class)->render($venta),
            ],
        );

        if ($trabajo->wasRecentlyCreated) {
            ProcessPrintJob::dispatch($trabajo->getKey())->afterCommit();
        }
    }

    public function reintentar(ColaVentaFiscal $cola): void
    {
        $cola->update([
            'estado' => EstadoColaVentaFiscal::PENDIENTE->value,
            'ultimo_error' => null,
            'proximo_intento_at' => null,
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
            $respuesta = $this->fiscalGateway->enviarVenta($config, $cola->payload_envio);

            DB::transaction(function () use ($cola, $venta, $respuesta): void {
                $venta->update([
                    'fiscal_sale_id' => $respuesta['codigoGeneracion'] ?? $respuesta['codigo_generacion'] ?? $respuesta['fiscal_sale_id'] ?? $venta->fiscal_sale_id,
                    'codigo_generacion' => $venta->codigo_generacion ?? ($respuesta['codigoGeneracion'] ?? $respuesta['codigo_generacion'] ?? null),
                    'numero_control' => $venta->numero_control ?? ($respuesta['numeroControl'] ?? $respuesta['numero_control'] ?? null),
                    'sello_recepcion' => $respuesta['selloRecepcion'] ?? $respuesta['sello_recepcion'] ?? $venta->sello_recepcion,
                    'estado' => EstadoVentaFiscal::SINCRONIZADO->value,
                    'receptor' => null,
                    'sincronizado_at' => now(),
                ]);

                $cola->update([
                    'estado' => EstadoColaVentaFiscal::ENVIADO->value,
                    'ultimo_error' => null,
                    'proximo_intento_at' => null,
                ]);

                DocumentoFiscal::query()
                    ->where('pedido_id', $venta->pedido_id)
                    ->where('estado', EstadoDocumentoFiscal::PENDIENTE->value)
                    ->update(['datos_solicitante' => null]);
            });

            return true;
        } catch (Throwable $exception) {
            if ($this->esFalloDeConexion($exception)) {
                $this->marcarContingencia($cola, $exception);
            } else {
                $venta->update(['estado' => EstadoVentaFiscal::ENVIO_FALLIDO->value]);
                $cola->update([
                    'estado' => EstadoColaVentaFiscal::FALLIDO->value,
                    'ultimo_error' => $exception->getMessage(),
                ]);
            }

            return false;
        }
    }

    private function marcarContingencia(ColaVentaFiscal $cola, ?Throwable $exception): void
    {
        $payload = $cola->payload_envio ?? [];

        if (empty($payload['codigo_generacion'])) {
            $payload['codigo_generacion'] = strtoupper((string) Str::uuid());
        }

        $payload['tipoModelo'] = 2;
        $payload['tipoOperacion'] = 2;
        $payload['tipoContingencia'] = 3;
        $payload['motivoContin'] = null;

        $desde = $cola->contingencia_desde ?? now();

        $cola->update([
            'payload_envio' => $payload,
            'contingencia' => true,
            'contingencia_desde' => $desde,
            'estado' => EstadoColaVentaFiscal::PENDIENTE->value,
            'ultimo_error' => $exception?->getMessage(),
            'proximo_intento_at' => now()->addMinutes($this->backoffMinutos((int) $cola->intentos)),
        ]);

        $venta = $cola->ventaFiscalPos;

        if ($venta) {
            $venta->update([
                'codigo_generacion' => $venta->codigo_generacion ?? $payload['codigo_generacion'],
                'fecha_emision' => $venta->fecha_emision ?? $desde,
                'estado' => EstadoVentaFiscal::NO->value,
            ]);
        }
    }

    private function esFalloDeConexion(Throwable $exception): bool
    {
        return $exception instanceof ConnectionException;
    }

    private function backoffMinutos(int $intentos): int
    {
        $escalones = [1, 2, 5, 15];
        $indice = min(max($intentos - 1, 0), count($escalones) - 1);

        return $escalones[$indice];
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
