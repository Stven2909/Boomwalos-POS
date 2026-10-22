<?php

namespace App\Services\Printing;

use App\Application\Printing\RenderKitchenComanda;
use App\Enums\AreaPreparacion;
use App\Enums\EstadoImpresion;
use App\Enums\TipoImpresora;
use App\Enums\TipoTrabajoImpresion;
use App\Models\DetallePedido;
use App\Models\Impresora;
use App\Models\Pedido;
use App\Models\TrabajoImpresion;
use Illuminate\Support\Collection;

/**
 * Agrupa las líneas pendientes de un pedido por área de preparación y crea un
 * trabajo de impresión (comanda) por área, enrutándolo a la impresora de esa
 * área dentro de la misma sucursal. No despacha: el llamador decide cuándo
 * encolar los trabajos.
 */
final class ComandaDispatcher
{
    public function __construct(
        private readonly RenderKitchenComanda $renderer,
    ) {}

    /**
     * @param  Collection<int, DetallePedido>  $lineas
     * @return Collection<int, TrabajoImpresion>
     */
    public function dispatch(Pedido $pedido, Collection $lineas): Collection
    {
        if ($lineas->isEmpty()) {
            return collect();
        }

        return $lineas
            ->groupBy(fn (DetallePedido $detalle): string => $this->areaFor($detalle)->value)
            ->map(fn (Collection $grupo, string $areaValue): TrabajoImpresion => $this->persistir(
                $pedido,
                $grupo->values(),
                AreaPreparacion::from($areaValue),
            ))
            ->values();
    }

    public function areaFor(DetallePedido $detalle): AreaPreparacion
    {
        if ($detalle->combo_id !== null) {
            return AreaPreparacion::COCINA;
        }

        $detalle->loadMissing('producto.categoria.parent');

        return $detalle->producto?->categoria?->areaResuelta() ?? AreaPreparacion::COCINA;
    }

    /**
     * @param  Collection<int, DetallePedido>  $lineas
     */
    private function persistir(Pedido $pedido, Collection $lineas, AreaPreparacion $area): TrabajoImpresion
    {
        $printer = Impresora::buscarPorArea(TipoImpresora::COMANDA, $area, $pedido->establecimiento_id);
        $contenido = $this->renderer->render($pedido, $lineas, $area);

        $lineIds = $lineas->pluck('id')->map(fn ($id): int => (int) $id)->all();
        sort($lineIds);

        $uid = hash('sha256', $pedido->getKey().'|COMANDA|'.$area->value.'|'.implode(',', $lineIds));

        $job = TrabajoImpresion::firstOrCreate(
            ['original_uid' => $uid],
            [
                'impresora_id' => $printer?->getKey(),
                'pedido_id' => $pedido->getKey(),
                'tipo_trabajo' => TipoTrabajoImpresion::COMANDA,
                'area' => $area,
                'estado' => $printer ? EstadoImpresion::PENDIENTE : EstadoImpresion::ERROR,
                'contenido' => $contenido,
                'ultimo_error' => $printer ? null : "No hay impresora de comanda (área {$area->label()}) en esta sucursal.",
            ],
        );

        $lineas->each(fn (DetallePedido $detalle): bool => $detalle->update(['tanda_id' => $job->getKey()]));

        return $job;
    }
}
