<?php

namespace App\Services\Platform;

use App\Context\TenantContext;
use App\Contracts\TenantConnectionResolverInterface;
use App\Models\Platform\PlatformTenant;
use Illuminate\Support\Facades\DB;

class TenantConnectionResolver implements TenantConnectionResolverInterface
{
    public function __construct(private readonly TenantContext $context) {}

    public function resolve(string $host): ?PlatformTenant
    {
        $slug = $this->slugFromHost($host);

        if ($slug === null) {
            return null;
        }

        return PlatformTenant::query()
            ->where('slug', $slug)
            ->where('status', 'active')
            ->first();
    }

    public function useTenant(PlatformTenant $tenant): void
    {
        if (! $tenant->isActive()) {
            throw new \RuntimeException('La empresa no está activa.');
        }

        $this->context->set($tenant);

        if (config('tenancy.mode') === 'single') {
            return;
        }

        $tenantConnection = $tenant->connection;

        if (! $tenantConnection) {
            throw new \RuntimeException('La empresa no tiene una conexión de base de datos configurada.');
        }

        $base = (array) config('database.connections.tenant', []);
        $connection = array_merge($base, array_filter([
            'driver' => $tenantConnection->driver,
            'host' => $tenantConnection->host,
            'port' => $tenantConnection->port,
            'database' => $tenantConnection->database,
            'username' => $tenantConnection->username,
            'password' => $tenantConnection->password,
            'unix_socket' => $tenantConnection->unix_socket,
        ], static fn (mixed $value): bool => $value !== null));

        if ($tenantConnection->options !== null) {
            $connection['options'] = $tenantConnection->options;
        }

        config(['database.connections.tenant' => $connection]);
        DB::purge('tenant');
        DB::reconnect('tenant');
        DB::setDefaultConnection('tenant');
    }

    public function reset(): void
    {
        $this->context->clear();

        if (config('tenancy.mode') !== 'single') {
            DB::purge('tenant');
            DB::setDefaultConnection(config('tenancy.fallback_connection'));
        }
    }

    private function slugFromHost(string $host): ?string
    {
        $host = strtolower(trim(explode(':', $host)[0]));
        $baseDomain = strtolower((string) config('tenancy.base_domain'));

        if ($host === '' || $host === 'localhost' || $host === '127.0.0.1') {
            return config('tenancy.default_slug');
        }

        if ($baseDomain !== '' && str_ends_with($host, '.' . $baseDomain)) {
            return trim(substr($host, 0, -strlen('.' . $baseDomain)), '.');
        }

        return config('tenancy.mode') === 'single'
            ? config('tenancy.default_slug')
            : null;
    }
}
