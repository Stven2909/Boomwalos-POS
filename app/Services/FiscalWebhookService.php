<?php

namespace App\Services;

use App\Enums\EstadoDocumentoFiscal;
use App\Enums\EstadoWebhookPos;
use App\Models\DocumentoFiscal;
use App\Models\FiscalSyncState;
use App\Models\VentaFiscalPos;
use App\Models\WebhookEventPos;

class FiscalWebhookService
{
    public function procesarEventoPosFact(array $datos): array
    {
        $codigoGen = (string) ($datos['codigoGeneracion'] ?? $datos['codigo_generacion'] ?? '');
        $sello = (string) ($datos['selloRecepcion'] ?? $datos['sello_recepcion'] ?? '');
        $numeroControl = (string) ($datos['numeroControl'] ?? $datos['numero_control'] ?? '');
        $status = strtoupper((string) ($datos['status'] ?? $datos['estado'] ?? 'PROCESSED'));

        if ($codigoGen === '') {
            return ['success' => false, 'message' => 'codigoGeneracion no proporcionado'];
        }

        // 1. Buscar en documentos fiscales por codigo_generacion
        $doc = DocumentoFiscal::query()->where('codigo_generacion', $codigoGen)->first();

        // 2. Si no se encuentra por código, buscar en ventas fiscales por fiscal_sale_id
        if (! $doc) {
            $venta = VentaFiscalPos::query()->where('fiscal_sale_id', $codigoGen)->first();
            if ($venta) {
                $doc = $venta->documentos()->where('estado', EstadoDocumentoFiscal::PENDIENTE->value)->first();
            }
        }

        if ($doc) {
            $nuevoEstado = in_array($status, ['PROCESSED', 'SEALED', 'EMITIDO'], true)
                ? EstadoDocumentoFiscal::EMITIDO->value
                : EstadoDocumentoFiscal::RECHAZADO->value;

            $doc->update([
                'estado' => $nuevoEstado,
                'sello_recepcion' => $sello ?: $doc->sello_recepcion,
                'numero_control' => $numeroControl ?: $doc->numero_control,
            ]);

            return [
                'success' => true,
                'documento_id' => $doc->getKey(),
                'estado' => $nuevoEstado,
            ];
        }

        return ['success' => true, 'message' => 'Evento recibido pero documento no vinculado'];
    }

    public function recibir(array $datos): WebhookEventPos
    {
        $venta = filled($datos['fiscal_sale_id'] ?? null)
            ? VentaFiscalPos::where('fiscal_sale_id', $datos['fiscal_sale_id'])->first()
            : null;

        $evento = WebhookEventPos::create([
            'establecimiento_id' => $venta?->establecimiento_id,
            'venta_fiscal_pos_id' => $venta?->getKey(),
            'secuencia' => (int) ($datos['secuencia'] ?? 0),
            'tipo' => (string) ($datos['tipo'] ?? 'DESCONOCIDO'),
            'payload' => (array) ($datos['payload'] ?? []),
            'estado' => EstadoWebhookPos::PENDIENTE->value,
            'recibido_at' => now(),
        ]);

        if ($venta !== null) {
            $this->reconciliar($venta->establecimiento_id);
        }

        return $evento->fresh();
    }

    public function reconciliar(int $establecimientoId): void
    {
        $state = FiscalSyncState::firstOrCreate(['establecimiento_id' => $establecimientoId]);

        while (true) {
            $siguiente = WebhookEventPos::query()
                ->where('establecimiento_id', $establecimientoId)
                ->where('estado', EstadoWebhookPos::PENDIENTE->value)
                ->where('secuencia', $state->ultima_secuencia_webhook + 1)
                ->orderBy('id')
                ->first();

            if (! $siguiente) {
                return;
            }

            $this->procesar($siguiente);

            $siguiente->update(['estado' => EstadoWebhookPos::PROCESADO->value]);
            $state->update(['ultima_secuencia_webhook' => $siguiente->secuencia]);
        }
    }

    private function procesar(WebhookEventPos $evento): void
    {
        if ($evento->tipo !== 'DTE_EMITIDO' || $evento->ventaFiscalPos === null) {
            return;
        }

        $documento = $evento->ventaFiscalPos->documentos()
            ->where('estado', EstadoDocumentoFiscal::PENDIENTE->value)
            ->first();

        if (! $documento) {
            return;
        }

        $documento->update([
            'estado' => EstadoDocumentoFiscal::EMITIDO->value,
            'codigo_generacion' => $evento->payload['codigo_generacion'] ?? $documento->codigo_generacion,
            'numero_control' => $evento->payload['numero_control'] ?? $documento->numero_control,
            'sello_recepcion' => $evento->payload['sello_recepcion'] ?? $documento->sello_recepcion,
        ]);
    }
}
