<?php

namespace App\Contracts;

use App\Models\ConfiguracionFiscal;

interface FiscalGatewayInterface
{
    public function enviarVenta(ConfiguracionFiscal $config, array $payload): array;

    /**
     * Consulta el estado fiscal de un DTE por su Código de Generación.
     * Se usa para reconciliar ventas diferidas cuando el webhook no llega
     * (el kiosko local no es alcanzable desde la nube).
     */
    public function consultarVenta(ConfiguracionFiscal $config, string $codigoGeneracion): array;
}
