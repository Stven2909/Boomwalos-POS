<?php

namespace App\Application\Fiscal;

use App\Models\ConfiguracionFiscal;
use Illuminate\Support\Facades\Http;

class FiscalClient
{
    public function enviarVenta(ConfiguracionFiscal $config, array $payload): array
    {
        $url = rtrim((string) config('fiscal.url'), '/')
            . '/' . trim((string) config('fiscal.prefix', '/api/fiscal/v1'), '/')
            . '/ventas';

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $firma = HmacSigner::sign((string) $body, (string) $config->cliente_secret);

        $respuesta = Http::withHeaders([
            (string) config('fiscal.hmac.key_header') => (string) $config->cliente_key,
            (string) config('fiscal.hmac.timestamp_header') => (string) time(),
            (string) config('fiscal.hmac.header') => $firma,
        ])
            ->withBody((string) $body, 'application/json')
            ->timeout((int) config('fiscal.timeout', 10))
            ->post($url);

        if ($respuesta->status() === 409) {
            throw new FiscalApiException(409, (string) ($respuesta->json('error') ?? 'CLAVE_REUTILIZADA'));
        }

        if (! $respuesta->successful()) {
            throw new FiscalApiException($respuesta->status(), (string) $respuesta->body());
        }

        return $respuesta->json();
    }
}
