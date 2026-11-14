<?php

namespace App\Services\Portal;

use App\Enums\EstadoLineaPedido;
use App\Enums\MetodoPago;
use App\Jobs\SyncPortalPedidoJob;
use App\Models\ConfiguracionFiscal;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\PortalSnapshotOutbox;
use Illuminate\Support\Facades\Log;

class PortalSnapshotRegistrar
{
    /** Guarda el snapshot en la misma transacción del cobro y lo encola al commit. */
    public function encolar(Pedido $pedido, Pago $pago): ?PortalSnapshotOutbox
    {
        $payload = $this->payload($pedido, $pago);

        if ($payload === null) {
            return null;
        }

        $tracking = (string) $payload['tracking'];

        $outbox = PortalSnapshotOutbox::query()->firstOrCreate(
            ['tracking' => $tracking],
            [
                'pedido_id' => $pedido->getKey(),
                'establecimiento_id' => $pedido->establecimiento_id,
                'payload' => $payload,
                'estado' => 'EN_COLA',
                'proximo_intento_at' => now(),
                'bloqueado_at' => now(),
            ],
        );

        if ($outbox->estado !== 'SINCRONIZADO') {
            $outbox->forceFill([
                'estado' => 'EN_COLA',
                'bloqueado_at' => now(),
            ])->save();

            SyncPortalPedidoJob::dispatch($outbox->getKey())->afterCommit();
        }

        return $outbox;
    }

    /**
     * Construye el snapshot mínimo (sin datos personales) para el portal.
     * Devuelve null si el pedido no tiene tracking completo o si el
     * establecimiento no tiene credenciales HMAC.
     */
    public function payload(Pedido $pedido, Pago $pago): ?array
    {
        $tracking = strtoupper(trim((string) $pedido->numero_seguimiento));

        if (preg_match('/^POS-\d{12}-[A-F0-9]{4}$/', $tracking) !== 1) {
            Log::error('No se generó el snapshot del portal: el pedido no tiene tracking completo.', [
                'pedido_id' => $pedido->getKey(),
            ]);

            return null;
        }

        $configuracion = $this->configuracionPara($pedido);

        if ($configuracion === null) {
            Log::warning('No se generó el snapshot del portal: falta configuración HMAC fiscal.', [
                'pedido_id' => $pedido->getKey(),
                'establecimiento_id' => $pedido->establecimiento_id,
            ]);

            return null;
        }

        $pedido->loadMissing(['establecimiento', 'detalles.producto', 'detalles.combo']);

        $items = $pedido->detalles
            ->filter(fn ($detalle): bool => $detalle->estado_linea === EstadoLineaPedido::ACTIVA)
            ->map(function ($detalle): array {
                $cantidad = (float) $detalle->cantidad;
                $precio = (float) $detalle->precio_unitario;

                return [
                    'nombre' => $detalle->combo?->nombre ?? $detalle->producto?->nombre ?? 'Producto',
                    'cantidad' => $cantidad,
                    'precio_unitario' => number_format($precio, 2, '.', ''),
                    'subtotal' => number_format($cantidad * $precio, 2, '.', ''),
                ];
            })
            ->values()
            ->all();

        return [
            'tracking' => $tracking,
            'codigo_corto' => $pedido->codigo_corto,
            'establecimiento' => (string) ($configuracion->codigo_establecimiento ?: 'M001'),
            'establecimiento_nombre' => (string) ($pedido->establecimiento?->nombre ?? $configuracion->razon_social),
            'puntoVenta' => (string) ($configuracion->codigo_punto_venta ?: 'P001'),
            'fecha' => ($pago->created_at ?? now())->toIso8601String(),
            'metodo_pago' => $pago->metodo_pago->value,
            'total' => number_format($pedido->total(), 2, '.', ''),
            'dte_esperado' => $pago->metodo_pago === MetodoPago::TARJETA,
            'items' => $items,
        ];
    }

    /** Configuración HMAC del establecimiento, solo si tiene credenciales completas. */
    public function configuracionPara(Pedido $pedido): ?ConfiguracionFiscal
    {
        $configuracion = ConfiguracionFiscal::query()
            ->where('establecimiento_id', $pedido->establecimiento_id)
            ->first();

        if ($configuracion === null
            || trim((string) $configuracion->cliente_key) === ''
            || trim((string) $configuracion->cliente_secret) === '') {
            return null;
        }

        return $configuracion;
    }
}
