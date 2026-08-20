<?php

namespace App\Application\Fiscal;

use App\Contracts\FiscalGatewayInterface;
use App\Models\ConfiguracionFiscal;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FiscalClient implements FiscalGatewayInterface
{
    public function enviarVenta(ConfiguracionFiscal $config, array $payload): array
    {
        $path = '/api/v1/pos/emitir';
        $url = rtrim((string) config('fiscal.url'), '/') . $path;

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $timestamp = time();
        $nonce = (string) Str::uuid();

        $firma = HmacSigner::sign('POST', $path, $timestamp, $nonce, (string) $body, (string) $config->cliente_secret);

        $respuesta = Http::withHeaders([
            (string) config('fiscal.hmac.key_header') => (string) $config->cliente_key,
            (string) config('fiscal.hmac.timestamp_header') => (string) $timestamp,
            (string) config('fiscal.hmac.nonce_header', 'X-Nonce') => $nonce,
            (string) config('fiscal.hmac.header') => $firma,
            'Idempotency-Key' => $payload['clave_reintento'] ?? (string) Str::uuid(),
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
