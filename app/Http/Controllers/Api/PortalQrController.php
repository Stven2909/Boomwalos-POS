<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Portal\PortalFiscalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalQrController extends Controller
{
    public function __construct(
        private readonly PortalFiscalService $portalFiscalService,
    ) {}

    /**
     * Consulta una orden por número de seguimiento o código corto.
     */
    public function consultarOrden(string $tracking): JsonResponse
    {
        $orden = $this->portalFiscalService->buscarOrden($tracking);

        if (! $orden) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró la orden solicitada. Verifica el número de tracking en tu ticket.',
            ], 404);
        }

        $responseData = array_merge($orden, [
            'orden' => $orden,
            'estadoSolicitud' => $orden['estado_solicitud'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $responseData,
        ]);
    }

    /**
     * Consulta el estado de una orden (compatible con WebFact).
     */
    public function estadoOrden(Request $request): JsonResponse
    {
        $tracking = (string) $request->query('trackingPOS', $request->query('tracking', ''));

        if ($tracking === '') {
            return response()->json([
                'success' => false,
                'message' => 'El parámetro trackingPOS es requerido.',
            ], 400);
        }

        $orden = $this->portalFiscalService->buscarOrden($tracking);

        if (! $orden) {
            return response()->json([
                'success' => false,
                'message' => 'Orden no encontrada.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'estadoDTE' => $orden['estado_solicitud'],
                'codigoGeneracion' => $orden['codigo_generacion'],
                'selloRecepcion' => $orden['sello_recepcion'],
            ],
        ]);
    }

    /**
     * Procesa la solicitud de factura electrónica del cliente.
     */
    public function solicitar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'trackingPOS' => 'required|string',
            'tipoDTE' => 'nullable|string',
            'nombre' => 'required|string|max:200',
            'nit' => 'nullable|string|max:30',
            'nrc' => 'nullable|string|max:30',
            'dui' => 'nullable|string|max:30',
            'email' => 'required|email|max:150',
            'telefono' => 'required|string|max:30',
            'giro' => 'nullable|string|max:250',
            'direccion' => 'nullable|string|max:300',
            'departamento' => 'nullable|string|max:100',
            'municipio' => 'nullable|string|max:100',
        ]);

        $resultado = $this->portalFiscalService->procesarSolicitudCliente($validated);

        $status = ($resultado['success'] ?? false) ? 200 : 422;

        return response()->json($resultado, $status);
    }
}
