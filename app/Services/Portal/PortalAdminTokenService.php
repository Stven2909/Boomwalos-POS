<?php

namespace App\Services\Portal;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class PortalAdminTokenService
{
    /**
     * Duración predeterminada del token: 24 horas (en segundos).
     */
    private const int TOKEN_TTL = 86400;

    /**
     * Genera un token Bearer criptográficamente seguro para un usuario administrador.
     */
    public function generateToken(User $user, int $ttl = self::TOKEN_TTL): string
    {
        $payload = [
            'user_id' => $user->getKey(),
            'usuario' => $user->usuario,
            'role' => 'administrador',
            'iat' => time(),
            'exp' => time() + $ttl,
        ];

        return Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * Valida y decodifica un token Bearer, retornando el usuario si es válido y tiene rol de administrador.
     */
    public function validateToken(string $token): ?User
    {
        try {
            $json = Crypt::decryptString($token);
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($payload)) {
                return null;
            }

            if (! isset($payload['user_id'], $payload['exp']) || $payload['exp'] < time()) {
                return null;
            }

            /** @var User|null $user */
            $user = User::query()->find($payload['user_id']);

            if (! $user || ! $user->hasRole('administrador')) {
                return null;
            }

            return $user;
        } catch (Throwable) {
            return null;
        }
    }
}
