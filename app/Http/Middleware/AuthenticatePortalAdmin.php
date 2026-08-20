<?php

namespace App\Http\Middleware;

use App\Services\Portal\PortalAdminTokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePortalAdmin
{
    public function __construct(
        private readonly PortalAdminTokenService $tokenService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization', '');

        if (! str_starts_with($header, 'Bearer ')) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado. Encabezado de autorización ausente o inválido.',
            ], 401);
        }

        $token = substr($header, 7);
        $user = $this->tokenService->validateToken($token);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Token de administrador inválido o expirado.',
            ], 401);
        }

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
