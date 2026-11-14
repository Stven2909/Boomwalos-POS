<?php

namespace App\Application\Fiscal;

use App\Contracts\FiscalGatewayInterface;
use App\Models\ConfiguracionFiscal;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FiscalClient implements FiscalGatewayInterface
{
    /** Envía el snapshot mínimo del pedido al portal de ApiFact usando HMAC. */
    public function enviarSnapshotPortalPedido(ConfiguracionFiscal $config, array $payload): array
    {
        $path = '/api/v1/portal/pedidos';
        $url = rtrim((string) config('fiscal.url'), '/').$path;
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $timestamp = time();
        $nonce = (string) Str::uuid();
        $firma = HmacSigner::sign('POST', $path, $timestamp, $nonce, $body, (string) $config->cliente_secret);

        $respuesta = Http::withHeaders([
            (string) config('fiscal.hmac.key_header') => (string) $config->cliente_key,
            (string) config('fiscal.hmac.timestamp_header') => (string) $timestamp,
            (string) config('fiscal.hmac.nonce_header', 'X-Nonce') => $nonce,
            (string) config('fiscal.hmac.header') => $firma,
        ])
            ->withBody($body, 'application/json')
            ->timeout((int) config('fiscal.timeout', 15))
            ->post($url);

        if (! $respuesta->successful()) {
            throw new FiscalApiException($respuesta->status(), (string) $respuesta->body());
        }

        return $respuesta->json() ?? [];
    }

    public function enviarVenta(ConfiguracionFiscal $config, array $payload): array
    {
        $path = '/api/v1/pos/emitir';
        $url = rtrim((string) config('fiscal.url'), '/').$path;

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

    /**
     * Consulta en ApiFact el estado fiscal y los identificadores de una orden
     * del portal (reconciliación del POS). Devuelve null si la orden no existe.
     */
    public function consultarPortalPedido(ConfiguracionFiscal $config, string $tracking): ?array
    {
        $path = '/api/v1/portal/pedidos/'.rawurlencode($tracking);
        $url = rtrim((string) config('fiscal.url'), '/').$path;

        $timestamp = time();
        $nonce = (string) Str::uuid();

        $firma = HmacSigner::sign('GET', $path, $timestamp, $nonce, '', (string) $config->cliente_secret);

        $respuesta = Http::withHeaders([
            (string) config('fiscal.hmac.key_header') => (string) $config->cliente_key,
            (string) config('fiscal.hmac.timestamp_header') => (string) $timestamp,
            (string) config('fiscal.hmac.nonce_header', 'X-Nonce') => $nonce,
            (string) config('fiscal.hmac.header') => $firma,
        ])
            ->timeout((int) config('fiscal.timeout', 10))
            ->get($url);

        if ($respuesta->status() === 404) {
            return null;
        }

        if (! $respuesta->successful()) {
            throw new FiscalApiException($respuesta->status(), (string) $respuesta->body());
        }

        return (array) ($respuesta->json('data') ?? []);
    }

    public function consultarVenta(ConfiguracionFiscal $config, string $codigoGeneracion): array
    {
        $path = '/api/v1/dte/'.rawurlencode($codigoGeneracion);
        $url = rtrim((string) config('fiscal.url'), '/').$path;

        $timestamp = time();
        $nonce = (string) Str::uuid();

        $firma = HmacSigner::sign('GET', $path, $timestamp, $nonce, '', (string) $config->cliente_secret);

        $respuesta = Http::withHeaders([
            (string) config('fiscal.hmac.key_header') => (string) $config->cliente_key,
            (string) config('fiscal.hmac.timestamp_header') => (string) $timestamp,
            (string) config('fiscal.hmac.nonce_header', 'X-Nonce') => $nonce,
            (string) config('fiscal.hmac.header') => $firma,
        ])
            ->timeout((int) config('fiscal.timeout', 10))
            ->get($url);

        if (! $respuesta->successful()) {
            throw new FiscalApiException($respuesta->status(), (string) $respuesta->body());
        }

        return $respuesta->json();
    }
}
