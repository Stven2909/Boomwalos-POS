<?php

namespace App\Http\Controllers\Fiscal;

use App\Http\Controllers\Controller;
use App\Services\FiscalWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhooksController extends Controller
{
    use MockFiscalTrait;

    /**
     * Simulador ENV-ONLY del endpoint externo de webhooks del proveedor.
     *
     * Almacena el evento (PENDIENTE si llega fuera de orden) y dispara la
     * reconciliación por secuencia cuando la venta ya es conocida.
     */
    public function store(Request $request, FiscalWebhookService $webhooks): JsonResponse
    {
        // 1. Caso de eventos automáticos desde POsFact-Laravel (DTE Sealed / Processed)
        if ($request->filled('codigoGeneracion') || $request->filled('codigo_generacion') || $request->hasHeader('X-POsFact-Event')) {
            // Si un proxy altera el Content-Type, $request->all() llega vacío;
            // $request->json() decodifica el cuerpo crudo sin depender del header.
            $datos = $request->json()->all() ?: $request->all();
            $resultado = $webhooks->procesarEventoPosFact($datos);

            return response()->json($resultado, 200);
        }

        // 2. Caso de webhook secuencial simulado / mock
        if (! $this->mockDisponible()) {
            abort(404);
        }

        $this->verificarFirma($request);

        $datos = $request->validate([
            'secuencia' => ['required', 'integer', 'min:1'],
            'tipo' => ['required', 'string', 'max:50'],
            'fiscal_sale_id' => ['sometimes', 'string', 'max:64'],
            'payload' => ['sometimes', 'array'],
        ]);

        $evento = $webhooks->recibir($datos);

        return response()->json([
            'evento_id' => $evento->getKey(),
            'estado' => $evento->estado->value,
        ], 202);
    }
}
