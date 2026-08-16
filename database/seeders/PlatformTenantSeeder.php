<?php

namespace Database\Seeders;

use App\Models\Platform\PlatformTenant;
use App\Models\Platform\PlatformUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PlatformTenantSeeder extends Seeder
{
    public function run(): void
    {
        PlatformTenant::firstOrCreate(
            ['slug' => config('tenancy.default_slug', 'demo')],
            [
                'display_name' => 'Pupusería Demo',
                'status' => 'active',
                'plan_code' => 'basic',
                'primary_color' => '#6B4E63',
                'secondary_color' => '#F6F1EE',
                'ticket_header' => 'Pupusería Demo',
                'ticket_footer' => 'Gracias por tu visita.',
            ],
        );

        $email = trim((string) env('POS_PLATFORM_ADMIN_EMAIL'));
        $password = trim((string) env('POS_PLATFORM_ADMIN_PASSWORD'));

        if ($email !== '' && $password !== '') {
            PlatformUser::updateOrCreate(
                ['email' => $email],
                [
                    'name' => 'Administrador de Plataforma',
                    'password' => Hash::make($password),
                ],
            );
        }
    }
}
