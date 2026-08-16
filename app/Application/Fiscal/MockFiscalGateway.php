<?php

namespace App\Application\Fiscal;

use App\Contracts\FiscalGatewayInterface;
use App\Models\ConfiguracionFiscal;

class MockFiscalGateway implements FiscalGatewayInterface
{
    public function enviarVenta(ConfiguracionFiscal $config, array $payload): array
    {
        return [
            'fiscal_sale_id' => 'MOCK-' . ($payload['clave_reintento'] ?? uniqid()),
            'estado' => 'RECIBIDA',
        ];
    }
}
