<?php

namespace App\Http\Controllers\Fiscal;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class VentasController extends Controller
{
    use MockFiscalTrait;

    /**
     * Simulador ENV-ONLY del endpoint externo POST /v1/ventas.
     *
     * Solo responde cuando FISCAL_MOCK_ENABLED=true y la aplicación no está
     * en producción. Exige firma HMAC y devuelve:
     *   202 {fiscal_sale_id, estado: "RECIBIDA", qr_url: null}
     *   409 si la misma clave_reintento llega con un payload distinto.
     */
    public function store(Request $request): JsonResponse
    {
        if (! $this->mockDisponible()) {
            abort(404);
        }

        $this->verificarFirma($request);

        $datos = $this->validar($request);

        $clave = $datos['clave_reintento'];
        $huella = hash('sha256', json_encode($datos, JSON_UNESCAPED_UNICODE));
        $claveCache = 'fiscal.mock.venta.' . $clave;

        $existente = Cache::get($claveCache);

        if ($existente !== null && ! hash_equals($existente['huella'], $huella)) {
            return response()->json([
                'error' => 'CLAVE_REUTILIZADA',
                'mensaje' => 'La clave_reintento ya fue utilizada con un contenido distinto.',
            ], 409);
        }

        $fiscalSaleId = $existente['id']
            ?? 'MOCK-' . Str::upper(substr(hash('sha256', $clave), 0, 12));

        Cache::put($claveCache, ['huella' => $huella, 'id' => $fiscalSaleId], now()->addDay());

        return response()->json([
            'fiscal_sale_id' => $fiscalSaleId,
            'estado' => 'RECIBIDA',
            'qr_url' => null,
        ], 202);
    }

    /**
     * @return array{clave_reintento: string, referencia: string, fecha_emision: string, monto_total: string}
     */
    private function validar(Request $request): array
    {
        $datos = $request->validate([
            'clave_reintento' => ['required', 'string', 'max:100'],
            'referencia' => ['required', 'string', 'max:50'],
            'fecha_emision' => ['required', 'date'],
            'monto_total' => ['required', 'numeric', 'min:0'],
            'metodo_pago' => ['sometimes', 'string', 'max:20'],
            'receptor' => ['sometimes', 'array'],
        ], [], [
            'clave_reintento' => 'clave de reintento',
            'referencia' => 'referencia de la venta',
            'fecha_emision' => 'fecha de emisión',
            'monto_total' => 'monto total',
        ]);

        return [
            'clave_reintento' => $datos['clave_reintento'],
            'referencia' => $datos['referencia'],
            'fecha_emision' => $datos['fecha_emision'],
            'monto_total' => (string) $datos['monto_total'],
            'metodo_pago' => $datos['metodo_pago'] ?? null,
            'receptor' => $datos['receptor'] ?? null,
        ];
    }
}
