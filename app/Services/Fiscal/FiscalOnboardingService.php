<?php

namespace App\Services\Fiscal;

use App\Models\ConfiguracionFiscal;
use App\Models\Establecimiento;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class FiscalOnboardingService
{
    /**
     * Aprovisiona un emisor en la API Fiscal y guarda las credenciales en el POS.
     *
     * @param array{
     *     establecimiento_id: int,
     *     ambiente?: string,
     *     nit: string,
     *     nrc?: string|null,
     *     razon_social: string,
     *     giro?: string|null,
     *     codigo_establecimiento?: string,
     *     codigo_punto_venta?: string,
     *     p12_base64: string,
     *     password: string
     * } $data
     * @return array{success: bool, message: string, client_id?: string, ambiente?: string, status?: int}
     */
    public function provisionar(array $data): array
    {
        $establecimientoId = (int) $data['establecimiento_id'];
        /** @var Establecimiento|null $establecimiento */
        $establecimiento = Establecimiento::query()->find($establecimientoId);

        if (! $establecimiento) {
            return [
                'success' => false,
                'message' => 'El establecimiento seleccionado no existe.',
            ];
        }

        $ambiente = (string) ($data['ambiente'] ?? '00');
        $nit = trim((string) ($data['nit'] ?? ''));
        $nrc = ! empty($data['nrc']) ? trim((string) $data['nrc']) : null;
        $razonSocial = trim((string) ($data['razon_social'] ?? ''));
        $giro = ! empty($data['giro']) ? trim((string) $data['giro']) : null;
        $codigoEstablecimiento = (string) ($data['codigo_establecimiento'] ?? '0001');
        $codigoPuntoVenta = (string) ($data['codigo_punto_venta'] ?? '001');
        $p12Base64 = (string) ($data['p12_base64'] ?? '');
        $password = (string) ($data['password'] ?? '');

        if ($nit === '' || $razonSocial === '' || $p12Base64 === '' || $password === '') {
            return [
                'success' => false,
                'message' => 'NIT, Razón Social, Certificado .p12 y Contraseña son obligatorios.',
            ];
        }

        $url = (string) config('fiscal.onboarding_url');
        $provisioningToken = (string) config('fiscal.provisioning_token');

        if (empty($provisioningToken)) {
            return [
                'success' => false,
                'message' => 'No se ha configurado PROVISIONING_TOKEN en el archivo .env del sistema.',
            ];
        }

        $payload = [
            'ambiente' => $ambiente,
            'emisor' => [
                'nit' => $nit,
                'nrc' => $nrc,
                'nombre' => $razonSocial,
                'giro' => $giro,
            ],
            'establecimiento' => [
                'codigo_mh' => $codigoEstablecimiento,
                'nombre' => $establecimiento->nombre,
            ],
            'punto_venta' => [
                'codigo_mh' => $codigoPuntoVenta,
                'nombre' => 'Caja Principal',
            ],
            'credencial' => array_filter([
                'p12_base64' => $p12Base64,
                'key_base64' => $p12Base64,
                'password' => $password,
                'usuario_mh' => ! empty($data['usuario_mh']) ? trim((string) $data['usuario_mh']) : null,
                'clave_mh' => ! empty($data['clave_mh']) ? (string) $data['clave_mh'] : null,
            ], fn ($value) => $value !== null),
        ];

        try {
            if (config('fiscal.mock.enabled')) {
                // Simulación para entornos de desarrollo / pruebas aisladas
                $clientId = 'pos-mock-' . strtoupper(Str::random(12));
                $secret = 'sec-mock-' . Str::random(32);
            } else {
                $response = Http::withHeaders([
                    'X-Provisioning-Token' => $provisioningToken,
                    'Accept' => 'application/json',
                ])
                    ->timeout((int) config('fiscal.timeout', 15))
                    ->post($url, $payload);

                if (! $response->successful()) {
                    $json = $response->json();
                    $errorMsg = is_array($json) && isset($json['message'])
                        ? (string) $json['message']
                        : $response->body();

                    Log::error("Fallo al aprovisionar emisor fiscal en {$url}: [{$response->status()}] {$errorMsg}");

                    return [
                        'success' => false,
                        'message' => 'La API Fiscal rechazó el aprovisionamiento: ' . $errorMsg,
                        'status' => $response->status(),
                    ];
                }

                $resultado = $response->json();
                Log::info("Respuesta de onboarding de la API Fiscal: " . json_encode($resultado));

                $clientId = (string) (
                    $resultado['client_id']
                    ?? $resultado['clientId']
                    ?? $resultado['cliente_key']
                    ?? $resultado['key']
                    ?? $resultado['api_key']
                    ?? $resultado['data']['client_id']
                    ?? $resultado['data']['clientId']
                    ?? $resultado['data']['cliente_key']
                    ?? $resultado['data']['key']
                    ?? $resultado['data']['api_key']
                    ?? $resultado['credenciales']['client_id']
                    ?? $resultado['credentials']['client_id']
                    ?? $resultado['emisor']['client_id']
                    ?? ''
                );

                $secret = (string) (
                    $resultado['secret']
                    ?? $resultado['client_secret']
                    ?? $resultado['cliente_secret']
                    ?? $resultado['api_secret']
                    ?? $resultado['secret_key']
                    ?? $resultado['data']['secret']
                    ?? $resultado['data']['client_secret']
                    ?? $resultado['data']['cliente_secret']
                    ?? $resultado['data']['api_secret']
                    ?? $resultado['data']['secret_key']
                    ?? $resultado['credenciales']['secret']
                    ?? $resultado['credentials']['secret']
                    ?? $resultado['emisor']['secret']
                    ?? ''
                );

                if ($clientId === '' || $secret === '') {
                    return [
                        'success' => false,
                        'message' => 'La API Fiscal respondió exitosamente pero no se encontraron las claves client_id / secret. Respuesta: ' . json_encode($resultado),
                    ];
                }
            }

            // Guardar configuración fiscal en el POS
            ConfiguracionFiscal::updateOrCreate(
                ['establecimiento_id' => $establecimientoId],
                [
                    'razon_social' => $razonSocial,
                    'nit' => $nit,
                    'nrc' => $nrc,
                    'ambiente' => $ambiente,
                    'giro' => $giro,
                    'codigo_establecimiento' => $codigoEstablecimiento,
                    'codigo_punto_venta' => $codigoPuntoVenta,
                    'cliente_key' => $clientId,
                    'cliente_secret' => $secret,
                    'fiscal_habilitada' => true,
                    'intentos_maximos' => 3,
                ]
            );

            Log::info("Emisor fiscal aprovisionado exitosamente para establecimiento #{$establecimientoId} ({$razonSocial}) con Client-ID: {$clientId}");

            return [
                'success' => true,
                'message' => '¡Facturación Electrónica activada exitosamente! Claves generadas y asociadas al establecimiento.',
                'client_id' => $clientId,
                'ambiente' => $ambiente,
            ];
        } catch (Throwable $e) {
            Log::error("Excepción durante aprovisionamiento fiscal: {$e->getMessage()}");

            return [
                'success' => false,
                'message' => 'Error de conexión con la API Fiscal: ' . $e->getMessage(),
            ];
        }
    }
}
