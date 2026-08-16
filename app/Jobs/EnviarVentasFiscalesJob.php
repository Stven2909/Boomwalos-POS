<?php

namespace App\Jobs;

use App\Application\Fiscal\FiscalOutboxService;
use App\Contracts\TenantConnectionResolverInterface;
use App\Models\Platform\PlatformTenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EnviarVentasFiscalesJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $ventaFiscalPosId,
        public readonly ?string $tenantSlug = null,
    ) {}

    public function handle(FiscalOutboxService $service, ?TenantConnectionResolverInterface $resolver = null): void
    {
        if (config('tenancy.mode') !== 'database' || $this->tenantSlug === null) {
            $service->enviarPendientes($this->ventaFiscalPosId);

            return;
        }

        if (! $resolver) {
            throw new \RuntimeException('El job fiscal multiempresa requiere un resolvedor de conexión.');
        }

        $tenant = PlatformTenant::query()
            ->where('slug', $this->tenantSlug)
            ->where('status', 'active')
            ->firstOrFail();
        $resolver->useTenant($tenant);

        try {
            $service->enviarPendientes($this->ventaFiscalPosId);
        } finally {
            $resolver->reset();
        }
    }
}
