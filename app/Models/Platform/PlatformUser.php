<?php

namespace App\Models\Platform;

use Filament\Models\Contracts\HasName;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class PlatformUser extends Authenticatable implements HasName, FilamentUser
{
    use Notifiable;

    protected $table = 'platform_users';

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('tenancy.mode') === 'single'
            ? config('tenancy.fallback_connection', config('database.default'))
            : 'platform';
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'platform';
    }
}
