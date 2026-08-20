<?php

namespace App\Application\Fiscal;

/**
 * Firma y verificación HMAC-SHA256 para la integración fiscal.
 *
 * Esquema:
 *   Cadena canónica: METHOD \n PATH \n TIMESTAMP \n NONCE \n sha256(BODY)
 *   Cabecera X-Signature : sha256=<hex>
 *   Cabecera X-Timestamp : segundos Unix UTC
 *   Cabecera X-Client-Id : clave del cliente
 *   Cabecera X-Nonce     : UUID o token único anti-repetición
 */
class HmacSigner
{
    public static function sign(
        string $method,
        string $path,
        int $timestamp,
        string $nonce,
        string $body,
        string $secret,
    ): string {
        $bodyHash = hash('sha256', $body);

        $payload = strtoupper($method) . "\n" .
            $path . "\n" .
            $timestamp . "\n" .
            $nonce . "\n" .
            $bodyHash;

        return 'sha256=' . hash_hmac('sha256', $payload, $secret);
    }

    public static function verify(
        string $method,
        string $path,
        int $timestamp,
        string $nonce,
        string $body,
        string $secret,
        string $signature,
    ): bool {
        $expected = self::sign($method, $path, $timestamp, $nonce, $body, $secret);

        return $expected !== '' && hash_equals($expected, $signature);
    }
}
