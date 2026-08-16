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
