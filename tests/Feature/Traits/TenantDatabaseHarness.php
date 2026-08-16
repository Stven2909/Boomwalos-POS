<?php

namespace Tests\Feature\Traits;

use App\Contracts\TenantConnectionResolverInterface;
use App\Models\Platform\PlatformTenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

trait TenantDatabaseHarness
{
    /** @var array<string, string> */
    private array $tenantHarnessFiles = [];

    /**
     * Crea tres bases SQLite temporales (platform, acme, beta) y configura el
     * modo `database` del tenancy. Las bases son archivos, no :memory:, porque
     * las conexiones purgadas perderían el esquema.
     */
    private function tenantHarnessSetUp(): void
    {
        $dir = sys_get_temp_dir().'/boomwalos-tenancy-'.bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);

        $this->tenantHarnessFiles = [
            'platform' => $dir.'/platform.sqlite',
            'acme' => $dir.'/tenant-acme.sqlite',
            'beta' => $dir.'/tenant-beta.sqlite',
        ];

        config(['tenancy.mode' => 'database']);

        config(['database.connections.platform' => [
            'driver' => 'sqlite',
            'database' => $this->tenantHarnessFiles['platform'],
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);

        config(['database.connections.tenant' => [
            'driver' => 'sqlite',
            'database' => $this->tenantHarnessFiles['acme'],
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);

        DB::purge('platform');
        DB::purge('tenant');
    }

    /**
     * Migra solo las tablas de plataforma (tenants + conexiones) contra la
     * conexión `platform`, reutilizando las migraciones ya existentes.
     */
    private function tenantHarnessMigratePlatform(): void
    {
        foreach ([
            'database/migrations/2026_08_15_000003_create_platform_tenants_table.php',
            'database/migrations/2026_08_15_000004_create_platform_tenant_connections_table.php',
        ] as $path) {
            Artisan::call('migrate', ['--database' => 'platform', '--path' => $path]);
        }
    }

    /**
     * Migra el esquema operativo completo contra una de las bases de tenant.
     * Usa la conexión llamada `tenant` porque el guard de las migraciones es
     * `Schema::getConnection()->getName() === 'tenant'`.
     */
    private function tenantHarnessMigrateOperative(string $file): void
    {
        config(['database.connections.tenant.database' => $file]);
        DB::purge('tenant');

        Artisan::call('migrate', ['--database' => 'tenant']);
    }

    private function tenantHarnessSeedPlatform(): void
    {
        $now = now()->toDateTimeString();

        DB::connection('platform')->table('platform_tenants')->insert([
            $this->platformTenantRow('acme', 'Acme POS', $now),
            $this->platformTenantRow('beta', 'Beta POS', $now),
        ]);

        $ids = DB::connection('platform')->table('platform_tenants')
            ->whereIn('slug', ['acme', 'beta'])
            ->pluck('id', 'slug');

        foreach (['acme', 'beta'] as $slug) {
            DB::connection('platform')->table('platform_tenant_connections')->insert([
                'tenant_id' => $ids[$slug],
                'driver' => 'sqlite',
                'host' => null,
                'port' => null,
                'database' => $this->tenantHarnessFiles[$slug],
                'username' => null,
                'password' => null,
                'unix_socket' => null,
                'options' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function platformTenantRow(string $slug, string $displayName, string $now): array
    {
        return [
            'slug' => $slug,
            'display_name' => $displayName,
            'status' => 'active',
            'plan_code' => 'basic',
            'logo_path' => null,
            'favicon_path' => null,
            'primary_color' => null,
            'secondary_color' => null,
            'ticket_header' => null,
            'ticket_footer' => null,
            'contact_phone' => null,
            'contact_email' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function tenantHarnessUse(string $slug): void
    {
        app(TenantConnectionResolverInterface::class)->useTenant(
            $this->tenantHarnessTenant($slug)
        );
    }

    private function tenantHarnessTenant(string $slug): PlatformTenant
    {
        return PlatformTenant::query()->where('slug', $slug)->firstOrFail();
    }

    /**
     * Conecta la conexión `tenant` a un archivo concreto y devuelve el conteo
     * de filas de una tabla. Restaura la conexión al finalizar.
     */
    private function tenantHarnessCount(string $file, string $table): int
    {
        $this->tenantHarnessPointTo($file);

        return (int) DB::connection('tenant')->table($table)->count();
    }

    private function tenantHarnessPointTo(string $file): void
    {
        config(['database.connections.tenant.database' => $file]);
        DB::purge('tenant');
        DB::reconnect('tenant');
    }

    /**
     * Purgar conexiones y borrar los archivos temporales. Debe ejecutarse con
     * la conexión por defecto restaurada a la de respaldo antes de
     * `parent::tearDown()` para que el rollback de RefreshDatabase no falle.
     */
    private function tenantHarnessTearDown(): void
    {
        foreach (['platform', 'tenant'] as $connection) {
            DB::purge($connection);
        }

        $dir = null;

        foreach ($this->tenantHarnessFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }

            $dir = dirname($file);
        }

        if ($dir !== null && is_dir($dir)) {
            @rmdir($dir);
        }
    }
}
