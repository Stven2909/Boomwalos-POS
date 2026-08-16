<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $admin = $this->firstOrCreateUser(
            email: $this->requiredEnv('POS_ADMIN_EMAIL', 'BOOMWALOS_ADMIN_EMAIL'),
            username: 'admin',
            name: 'Administrador General',
            password: $this->requiredEnv('POS_ADMIN_PASSWORD', 'BOOMWALOS_ADMIN_PASSWORD'),
        );
        $admin->syncRoles('administrador');

        $cashier = $this->firstOrCreateUser(
            email: $this->requiredEnv('POS_CASHIER_EMAIL', 'BOOMWALOS_CASHIER_EMAIL'),
            username: $this->requiredEnv('POS_CASHIER_CODE', 'BOOMWALOS_CASHIER_CODE'),
            name: 'Lucía García',
            password: $this->requiredEnv('POS_CASHIER_PIN', 'BOOMWALOS_CASHIER_PIN'),
        );
        $cashier->syncRoles('cajero');
    }

    private function requiredEnv(string $key, ?string $legacyKey = null): string
    {
        $value = trim((string) env($key));

        if ($value === '' && $legacyKey !== null) {
            $value = trim((string) env($legacyKey));
        }

        if ($value === '') {
            throw new \RuntimeException("Configura {$key} en el archivo .env antes de ejecutar los seeders de usuarios.");
        }

        return $value;
    }

    private function firstOrCreateUser(
        string $email,
        string $username,
        string $name,
        string $password,
    ): User {
        return User::updateOrCreate(
            ['email' => $email],
            [
                'nombre' => $name,
                'usuario' => $username,
                'password' => Hash::make($password),
            ],
        );
    }
}
