<?php

namespace App\Application\Kitchen;

use App\Contracts\KitchenDispatcherInterface;
use App\Enums\EstadoImpresion;
use App\Enums\TipoImpresora;
use App\Enums\TipoTrabajoImpresion;
use App\Models\Impresora;
use App\Models\TandaPedido;
use App\Models\TrabajoImpresion;

class QueueKitchenBatch implements KitchenDispatcherInterface
{
    public function dispatch(TandaPedido $batch): ?TrabajoImpresion
    {
        return $this->handle($batch);
    }

    public function handle(TandaPedido $batch): ?TrabajoImpresion
    {
        $printer = Impresora::query()
            ->where('tipo', TipoImpresora::COMANDA->value)
            ->orderBy('id')
            ->first();

        if (! $printer) {
            return null;
        }

        $batch->load([
            'pedido.mesa',
            'pedido.establecimiento',
            'detalles.producto',
            'detalles.combo',
            'detalles.detallePedidoNotas.notaCocina',
        ]);

        return TrabajoImpresion::firstOrCreate(
            [
                'impresora_id' => $printer->getKey(),
                'tanda_id' => $batch->getKey(),
            ],
            [
                'pedido_id' => $batch->pedido_id,
                'tipo_trabajo' => TipoTrabajoImpresion::COMANDA,
                'estado' => EstadoImpresion::PENDIENTE,
                'contenido' => $this->render($batch),
            ],
        );
    }

    private function render(TandaPedido $batch): string
    {
        $pedido = $batch->pedido;
        $destination = $pedido->mesa
            ? 'MESA ' . $pedido->mesa->numero
            : 'PARA LLEVAR · MOSTRADOR';

        $lines = [
            mb_strtoupper((string) ($pedido->establecimiento?->nombre ?: 'POS')),
            'COMANDA · TANDA ' . $batch->numero_tanda,
            $destination,
            'PEDIDO ' . $pedido->numero_seguimiento,
            str_repeat('-', 32),
        ];

        foreach ($batch->detalles as $detail) {
            $name = $detail->combo?->nombre ?? $detail->producto?->nombre ?? 'Producto';
            $lines[] = $detail->cantidad . ' x ' . $name;

            foreach ($detail->seleccion_combo ?? [] as $group) {
                foreach ($group['items'] ?? [] as $item) {
                    $lines[] = '  - ' . $item['cantidad'] . ' ' . $item['nombre'];
                }
            }

            foreach ($detail->detallePedidoNotas as $note) {
                $lines[] = '  * ' . $note->notaCocina?->nombre;
            }
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }
}
