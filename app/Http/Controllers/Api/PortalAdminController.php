<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoDocumentoFiscal;
use App\Http\Controllers\Controller;
use App\Models\DocumentoFiscal;
use App\Models\User;
use App\Services\Portal\PortalAdminTokenService;
use App\Services\Portal\PortalFiscalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PortalAdminController extends Controller
{
    public function __construct(
        private readonly PortalAdminTokenService $tokenService,
        private readonly PortalFiscalService $portalFiscalService,
    ) {}

    /**
     * Autenticación de administradores para el panel de WebFact.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'usuario' => 'required_without:email|string',
            'email' => 'required_without:usuario|string',
            'password' => 'required|string',
        ]);

        $credencial = $validated['usuario'] ?? $validated['email'];

        /** @var User|null $user */
        $user = User::query()
            ->where('usuario', $credencial)
            ->orWhere('email', $credencial)
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciales inválidas. Por favor verifica tus datos.',
            ], 401);
        }

        if (! $user->hasRole('administrador')) {
            return response()->json([
                'success' => false,
                'message' => 'Acceso denegado. Este usuario no cuenta con privilegios de administrador.',
            ], 403);
        }

        $token = $this->tokenService->generateToken($user);

        return response()->json([
            'success' => true,
            'message' => 'Inicio de sesión exitoso.',
            'token' => $token,
            'user' => [
                'id' => $user->getKey(),
                'nombre' => $user->nombre,
                'usuario' => $user->usuario,
                'email' => $user->email,
                'role' => 'administrador',
            ],
            'modo_emision' => $this->portalFiscalService->obtenerModoEmision(),
        ]);
    }

    /**
     * Listado de solicitudes de facturas con filtros.
     */
    public function solicitudes(Request $request): JsonResponse
    {
        $query = DocumentoFiscal::query()
            ->with(['pedido.detalles.producto', 'pedido.detalles.combo', 'pedido.pago', 'pedido.mesa', 'pedido.establecimiento'])
            ->latest('id');

        if ($request->filled('estado')) {
            $estadoStr = strtoupper((string) $request->query('estado'));
            if ($estado = EstadoDocumentoFiscal::tryFrom($estadoStr)) {
                $query->where('estado', $estado);
            }
        }

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where(function (Builder $q) use ($search): void {
                $q->whereHas('pedido', function (Builder $pq) use ($search): void {
                    $pq->where('numero_seguimiento', 'like', "%{$search}%");
                })->orWhere('datos_solicitante', 'like', "%{$search}%");
            });
        }

        $solicitudes = $query->paginate(25);

        return response()->json([
            'success' => true,
            'data' => $solicitudes,
            'stats' => [
                'pendientes' => DocumentoFiscal::where('estado', EstadoDocumentoFiscal::PENDIENTE)->count(),
                'emitidos' => DocumentoFiscal::where('estado', EstadoDocumentoFiscal::EMITIDO)->count(),
                'rechazados' => DocumentoFiscal::where('estado', EstadoDocumentoFiscal::RECHAZADO)->count(),
            ],
        ]);
    }

    /**
     * Actualiza los datos del cliente en una solicitud antes de emitir.
     */
    public function actualizarSolicitud(Request $request, int $id): JsonResponse
    {
        /** @var DocumentoFiscal $docFiscal */
        $docFiscal = DocumentoFiscal::query()->findOrFail($id);

        if ($docFiscal->estado === EstadoDocumentoFiscal::EMITIDO) {
            return response()->json([
                'success' => false,
                'message' => 'No se pueden modificar los datos de una factura ya emitida.',
            ], 422);
        }

        $validated = $request->validate([
            'nombre' => 'nullable|string|max:200',
            'nit' => 'nullable|string|max:30',
            'nrc' => 'nullable|string|max:30',
            'dui' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:150',
            'telefono' => 'nullable|string|max:30',
            'giro' => 'nullable|string|max:250',
            'direccion' => 'nullable|string|max:300',
            'departamento' => 'nullable|string|max:100',
            'municipio' => 'nullable|string|max:100',
        ]);

        $datosActuales = $docFiscal->datos_solicitante ?? [];
        $datosActualizados = array_merge($datosActuales, array_filter($validated, fn ($val) => $val !== null));

        $docFiscal->update([
            'datos_solicitante' => $datosActualizados,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Datos de la solicitud actualizados correctamente.',
            'data' => $docFiscal,
        ]);
    }

    /**
     * Emisión manual de la factura electrónica por parte del administrador.
     */
    public function generarDte(int $id): JsonResponse
    {
        $resultado = $this->portalFiscalService->generarDteSolicitud($id);

        $status = ($resultado['success'] ?? false) ? 200 : 422;

        return response()->json($resultado, $status);
    }

    /**
     * Rechazo de una solicitud.
     */
    public function rechazar(Request $request, int $id): JsonResponse
    {
        $motivo = (string) $request->input('motivo', 'Datos fiscales incorrectos o incompletos.');

        $docFiscal = $this->portalFiscalService->rechazarSolicitud($id, $motivo);

        return response()->json([
            'success' => true,
            'message' => 'Solicitud rechazada.',
            'data' => $docFiscal,
        ]);
    }

    /**
     * Consulta la configuración de emisión del portal.
     */
    public function obtenerConfiguracion(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'modo_emision' => $this->portalFiscalService->obtenerModoEmision(),
                'modos_disponibles' => [
                    [
                        'id' => PortalFiscalService::MODO_MANUAL,
                        'nombre' => 'Validación Manual',
                        'descripcion' => 'Todas las solicitudes quedan pendientes hasta que un administrador las revise y apruebe.',
                    ],
                    [
                        'id' => PortalFiscalService::MODO_AUTOMATICO,
                        'nombre' => 'Emisión Automática',
                        'descripcion' => 'Todas las solicitudes se emiten y envían al instante sin requerir intervención humana.',
                    ],
                    [
                        'id' => PortalFiscalService::MODO_HIBRIDO,
                        'nombre' => 'Modo Híbrido (Recomendado)',
                        'descripcion' => 'Facturas Consumidor Final (01) se emiten en automático; Créditos Fiscales (03) pasan a validación.',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Actualiza el modo de emisión del portal.
     */
    public function guardarConfiguracion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'modo_emision' => 'required|string|in:MANUAL,AUTOMATICO,HIBRIDO,manual,automatico,hibrido',
        ]);

        $modoNuevo = $this->portalFiscalService->guardarModoEmision($validated['modo_emision']);

        return response()->json([
            'success' => true,
            'message' => 'Modo de emisión actualizado correctamente.',
            'data' => [
                'modo_emision' => $modoNuevo,
            ],
        ]);
    }
}
