<?php

namespace App\Services;

use App\Enums\EstadoDocumentoFiscal;
use App\Enums\EstadoVentaFiscal;
use App\Enums\TipoDocumento;
use App\Models\DocumentoFiscal;
use App\Models\User;
use App\Models\VentaFiscalPos;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class FiscalDocumentoService
{
    /**
     * Solicita un documento fiscal (FACTURA / CCF) para una venta ya
     * sincronizada. La solicitud vence a las 48 horas.
     */
    public function solicitar(
        VentaFiscalPos $venta,
        TipoDocumento $tipo,
        array $datosSolicitante,
        User $actor,
    ): DocumentoFiscal {
        if (! $actor->can('solicitar_documento_fiscal')) {
            throw new AuthorizationException('No tienes permiso para solicitar documentos fiscales.');
        }

        if ($venta->estado !== EstadoVentaFiscal::SINCRONIZADO) {
            throw ValidationException::withMessages([
                'venta' => 'Solo puedes solicitar un documento para una venta sincronizada.',
            ]);
        }

        $this->validarDatosSolicitante($datosSolicitante);

        $existente = DocumentoFiscal::query()
            ->where('pedido_id', $venta->pedido_id)
            ->where('tipo_documento', $tipo)
            ->first();

        if ($existente !== null && $existente->isSolicitable()) {
            throw ValidationException::withMessages([
                'documento' => 'Ya existe una solicitud vigente para este documento.',
            ]);
        }

        $datos = [
            'venta_fiscal_pos_id' => $venta->getKey(),
            'tipo_documento' => $tipo,
            'estado' => EstadoDocumentoFiscal::PENDIENTE->value,
            'datos_solicitante' => $datosSolicitante,
            'numero_control' => null,
            'codigo_generacion' => null,
            'sello_recepcion' => null,
            'solicitado_at' => now(),
            'expires_at' => now()->addHours((int) config('fiscal.documento.expires_hours', 48)),
        ];

        if ($existente !== null) {
            $existente->update($datos);

            return $existente->fresh();
        }

        return DocumentoFiscal::create([
            'pedido_id' => $venta->pedido_id,
            ...$datos,
        ]);
    }

    private function validarDatosSolicitante(array $datos): void
    {
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        $documento = trim((string) ($datos['documento'] ?? ''));
        $tipo = trim((string) ($datos['tipo_documento'] ?? ''));

        if ($nombre === '' || $documento === '' || ! in_array($tipo, ['NIT', 'DUI', 'OTRO'], true)) {
            throw ValidationException::withMessages([
                'datos_solicitante' => 'Ingresa el nombre, el documento y su tipo (NIT, DUI u OTRO) del receptor.',
            ]);
        }
    }
}
