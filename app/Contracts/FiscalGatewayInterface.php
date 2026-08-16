<?php

namespace App\Contracts;

use App\Models\ConfiguracionFiscal;

interface FiscalGatewayInterface
{
    public function enviarVenta(ConfiguracionFiscal $config, array $payload): array;
}
