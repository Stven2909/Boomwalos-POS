<?php

namespace App\Http\Controllers\Fiscal;

use App\Application\Fiscal\HmacSigner;
use Illuminate\Http\Request;

trait MockFiscalTrait
{
    private function mockDisponible(): bool
    {
        return config('fiscal.mock.enabled') && config('app.env') !== 'production';
    }

    private function verificarFirma(Request $request): void
    {
        $secret = (string) config('fiscal.mock.secret');

        if ($secret === '') {
            abort(401, 'FIRMA_NO_CONFIGURADA');
        }

        $firma = $request->header(config('fiscal.hmac.header'));
        $timestamp = $request->header(config('fiscal.hmac.timestamp_header'));

        if (! is_string($firma) || $firma === '') {
            abort(401, 'FIRMA_REQUERIDA');
        }

        if (! is_numeric($timestamp) || abs(time() - (int) $timestamp) > (int) config('fiscal.hmac.clock_skew')) {
            abort(401, 'MARCA_TIEMPO_VENCIDA');
        }

        if (! HmacSigner::verify($request->getContent(), $secret, $firma)) {
            abort(401, 'FIRMA_INVALIDA');
        }
    }
}
