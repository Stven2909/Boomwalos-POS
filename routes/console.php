<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Contracts\TenantConnectionResolverInterface;
use App\Models\Platform\PlatformTenant;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('platform:migrate', function (): void {
    foreach ([
        '2026_08_15_000003_create_platform_tenants_table.php',
        '2026_08_15_000004_create_platform_tenant_connections_table.php',
        '2026_08_15_000007_create_platform_users_table.php',
    ] as $migration) {
        $this->call('migrate', [
            '--database' => 'platform',
            '--path' => database_path('migrations/' . $migration),
            '--realpath' => true,
            '--force' => true,
        ]);
    }
})->purpose('Migra las tablas centrales de la plataforma POS.');

Artisan::command('tenants:migrate', function (): void {
    if (config('tenancy.mode') !== 'database') {
        $this->warn('TENANT_DATABASE_MODE no está en database; se omite la migración multiempresa.');

        return;
    }

    $resolver = app(TenantConnectionResolverInterface::class);

    PlatformTenant::query()
        ->where('status', 'active')
        ->each(function (PlatformTenant $tenant) use ($resolver): void {
            $resolver->useTenant($tenant);
            $this->line("Migrando tenant {$tenant->slug}...");
            $this->call('migrate', [
                '--database' => 'tenant',
                '--path' => database_path('migrations'),
                '--realpath' => true,
                '--force' => true,
            ]);
        });

    $resolver->reset();
})->purpose('Migra el esquema operativo de cada empresa registrada.');
