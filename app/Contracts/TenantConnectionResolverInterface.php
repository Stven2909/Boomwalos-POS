<?php

namespace App\Contracts;

use App\Models\Platform\PlatformTenant;

interface TenantConnectionResolverInterface
{
    public function resolve(string $host): ?PlatformTenant;

    public function useTenant(PlatformTenant $tenant): void;

    public function reset(): void;
}
