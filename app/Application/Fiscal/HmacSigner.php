<?php

namespace App\Application\Fiscal;

/**
 * Firma y verificación HMAC-SHA256 para la integración fiscal.
 *
 * Esquema (documentado en Contrato_API_Fiscal_v1.md):
 *   Cabecera  X-Fiscal-Hmac        : sha256=<hex> calculado sobre el cuerpo crudo
 *   Cabecera  X-Fiscal-Timestamp   : segundos Unix UTC
 *   Cabecera  X-Fiscal-Key         : clave pública del cliente (establecimiento)
 */
class HmacSigner
{
    public static function sign(string $payload, string $secret): string
    {
        return 'sha256=' . hash_hmac('sha256', $payload, $secret);
    }

    public static function verify(string $payload, string $secret, string $signature): bool
    {
        $expected = self::sign($payload, $secret);

        return $expected !== '' && hash_equals($expected, $signature);
    }
}
