<?php

namespace App\Services\Portal;

use App\Application\Fiscal\FiscalClient;
use App\Enums\EstadoDocumentoFiscal;
use App\Enums\EstadoVentaFiscal;
use App\Models\ConfiguracionFiscal;
use App\Models\DocumentoFiscal;
use App\Models\PortalSnapshotOutbox;
use App\Models\VentaFiscalPos;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reconciliación por pull del estado fiscal de las órdenes del portal.
 *
 * ApiFact es la fuente de verdad: aquí solo se copian el estado y los
 * identificadores del DTE a los registros locales. No emite, no reintenta y
 * **no reimprime**: la reimpresión del DTE final sigue reservada a la
 * contingencia (`pos:reconciliar-fiscales`) y a la recuperación manual.
 */
class PortalReconciliador
{
    public function __construct(private readonly FiscalClient $client) {}

    /**
     * @return array{revisadas:int, actualizadas:int, sin_cambios:int, errores:int}
     */
    public function reconciliar(int $limit = 100): array
    {
        $resumen = ['revisadas' => 0, 'actualizadas' => 0, 'sin_cambios' => 0, 'errores' => 0];

        $desde = now()->subDays(max(1, (int) config('portal.reconciliacion_dias', 7)));

        $pendientes = PortalSnapshotOutbox::query()
            ->where('estado', 'SINCRONIZADO')
            ->where('sincronizado_at', '>=', $desde)
            ->where(function ($query): void {
                $query->whereIn('pedido_id', VentaFiscalPos::query()
                    ->select('pedido_id')
                    ->where(function ($venta): void {
                        $venta->whereNull('sello_recepcion')
                            ->orWhere('estado', '!=', EstadoVentaFiscal::SINCRONIZADO->value);
                    }))
                    ->orWhereIn('pedido_id', DocumentoFiscal::query()
                        ->select('pedido_id')
                        ->where('estado', EstadoDocumentoFiscal::PENDIENTE->value));
            })
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();

        foreach ($pendientes as $outbox) {
            $resumen['revisadas']++;

            $configuracion = ConfiguracionFiscal::query()
                ->where('establecimiento_id', $outbox->establecimiento_id)
                ->first();

            if ($configuracion === null) {
                $resumen['errores']++;

                continue;
            }

            try {
                $orden = $this->client->consultarPortalPedido($configuracion, $outbox->tracking);
            } catch (Throwable $exception) {
                // Sin enlace o ApiFact no disponible: se reintenta en la próxima
                // pasada; la orden del portal no cambia por este fallo.
                Log::warning('No se pudo reconciliar la orden del portal.', [
                    'tracking' => $outbox->tracking,
                    'error' => $exception->getMessage(),
                ]);

                $resumen['errores']++;

                continue;
            }

            if ($orden === null) {
                $resumen['sin_cambios']++;

                continue;
            }

            $resumen[$this->aplicar($outbox, $orden) ? 'actualizadas' : 'sin_cambios']++;
        }

        return $resumen;
    }

    /** Copia estado e identificadores a los registros locales del pedido. */
    private function aplicar(PortalSnapshotOutbox $outbox, array $orden): bool
    {
        $estadoVisible = strtoupper(trim((string) ($orden['estado_visible'] ?? '')));
        $codigo = $this->texto($orden['codigo_generacion'] ?? null);
        $control = $this->texto($orden['numero_control'] ?? null);
        $sello = $this->texto($orden['sello_recepcion'] ?? null);
        $tipoDocumento = strtoupper(trim((string) ($orden['tipo_documento'] ?? '')));

        return DB::transaction(function () use ($outbox, $estadoVisible, $codigo, $control, $sello, $tipoDocumento): bool {
            $cambios = false;

            $venta = VentaFiscalPos::query()
                ->where('pedido_id', $outbox->pedido_id)
                ->lockForUpdate()
                ->first();

            if ($venta !== null) {
                $atributos = [];

                foreach (['codigo_generacion' => $codigo, 'numero_control' => $control, 'sello_recepcion' => $sello] as $campo => $valor) {
                    if ($valor !== null && $venta->{$campo} !== $valor) {
                        $atributos[$campo] = $valor;
                    }
                }

                // Sincronizada significa "ApiFact tiene el DTE"; el sello solo
                // existe cuando Hacienda ya lo confirmó.
                if (in_array($estadoVisible, ['EMITIDA', 'ANULADA'], true)
                    && $venta->estado !== EstadoVentaFiscal::SINCRONIZADO) {
                    $atributos['estado'] = EstadoVentaFiscal::SINCRONIZADO->value;
                    $atributos['sincronizado_at'] = $venta->sincronizado_at ?? now();
                }

                if ($atributos !== []) {
                    $venta->update($atributos);
                    $cambios = true;
                }
            }

            $documentos = DocumentoFiscal::query()
                ->where('pedido_id', $outbox->pedido_id)
                ->when($tipoDocumento !== '', fn ($query) => $query->where('tipo_documento', $tipoDocumento))
                ->lockForUpdate()
                ->get();

            foreach ($documentos as $documento) {
                $atributos = [];

                foreach (['codigo_generacion' => $codigo, 'numero_control' => $control, 'sello_recepcion' => $sello] as $campo => $valor) {
                    if ($valor !== null && $documento->{$campo} !== $valor) {
                        $atributos[$campo] = $valor;
                    }
                }

                $nuevoEstado = $this->estadoDocumento($estadoVisible, $documento->estado);

                if ($nuevoEstado !== null) {
                    $atributos['estado'] = $nuevoEstado;
                }

                if ($atributos !== []) {
                    $documento->update($atributos);
                    $cambios = true;
                }
            }

            return $cambios;
        });
    }

    /**
     * El estado local solo avanza: un documento ya emitido no retrocede a
     * pendiente ni a rechazado por una lectura tardía.
     */
    private function estadoDocumento(string $estadoVisible, EstadoDocumentoFiscal $actual): ?EstadoDocumentoFiscal
    {
        if ($actual === EstadoDocumentoFiscal::EMITIDO) {
            return null;
        }

        return match ($estadoVisible) {
            'EMITIDA', 'ANULADA' => EstadoDocumentoFiscal::EMITIDO,
            'RECHAZADA' => EstadoDocumentoFiscal::RECHAZADO,
            default => null,
        };
    }

    private function texto(mixed $valor): ?string
    {
        $texto = trim((string) ($valor ?? ''));

        return $texto === '' ? null : $texto;
    }
}
