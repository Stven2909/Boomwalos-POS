<?php

namespace Database\Seeders;

use App\Contracts\TenantConnectionResolverInterface;
use App\Models\Platform\PlatformTenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PlatformTenantSeeder::class);

        if (config('tenancy.mode') === 'database') {
            $tenant = PlatformTenant::query()
                ->where('slug', config('tenancy.default_slug'))
                ->where('status', 'active')
                ->firstOrFail();
            $resolver = app(TenantConnectionResolverInterface::class);
            $resolver->useTenant($tenant);
        }

        $this->call(RolesPermissionsSeeder::class);
        $this->call(DemoUsersSeeder::class);
        $this->call(DemoPosSeeder::class);

        if (config('tenancy.mode') === 'database') {
            app(TenantConnectionResolverInterface::class)->reset();
        }
    }
}
