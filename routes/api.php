<?php

use App\Http\Controllers\Fiscal\VentasController;
use App\Http\Controllers\Fiscal\WebhooksController;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Fiscal (v1)
|--------------------------------------------------------------------------
|
| Endpoints del proveedor fiscal simulados (ENV-ONLY, FISCAL_MOCK_ENABLED)
| y recepción de webhooks del proveedor.
|
*/

Route::prefix('fiscal/v1')->middleware(ResolveTenant::class)->group(function (): void {
    Route::post('/ventas', [VentasController::class, 'store']);
    Route::post('/webhooks', [WebhooksController::class, 'store']);
});

/*
|--------------------------------------------------------------------------
| Portal QR / WebFact API (v1)
|--------------------------------------------------------------------------
|
| Endpoints públicos para clientes y protegidos para administradores de WebFact.
|
*/

use App\Http\Controllers\Api\PortalAdminController;
use App\Http\Controllers\Api\PortalQrController;
use App\Http\Middleware\AuthenticatePortalAdmin;

Route::prefix('v1/portal-qr')->middleware(ResolveTenant::class)->group(function (): void {
    Route::get('/orden/{tracking}', [PortalQrController::class, 'consultarOrden']);
    Route::get('/estado', [PortalQrController::class, 'estadoOrden']);
    Route::post('/solicitar', [PortalQrController::class, 'solicitar']);
});

Route::prefix('v1/portal-admin')->middleware(ResolveTenant::class)->group(function (): void {
    Route::post('/login', [PortalAdminController::class, 'login']);

    Route::middleware(AuthenticatePortalAdmin::class)->group(function (): void {
        Route::get('/solicitudes', [PortalAdminController::class, 'solicitudes']);
        Route::put('/solicitudes/{id}', [PortalAdminController::class, 'actualizarSolicitud']);
        Route::post('/solicitudes/{id}/generar', [PortalAdminController::class, 'generarDte']);
        Route::post('/solicitudes/{id}/rechazar', [PortalAdminController::class, 'rechazar']);
        Route::get('/configuracion', [PortalAdminController::class, 'obtenerConfiguracion']);
        Route::put('/configuracion', [PortalAdminController::class, 'guardarConfiguracion']);
    });
});

