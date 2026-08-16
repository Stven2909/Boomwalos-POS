<?php

namespace App\Context;

use App\Models\Platform\PlatformTenant;

class TenantContext
{
    private ?PlatformTenant $tenant = null;

    public function set(?PlatformTenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function current(): ?PlatformTenant
    {
        return $this->tenant;
    }

    public function require(): PlatformTenant
    {
        return $this->tenant ?? throw new \RuntimeException('No se ha resuelto la empresa para esta solicitud.');
    }

    public function clear(): void
    {
        $this->tenant = null;
    }
}
