<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\HasName;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasName, FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $table = 'usuarios';

    protected $fillable = [
        'nombre',
        'usuario',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'usuario_id');
    }

    public function getFilamentName(): string
    {
        return $this->nombre;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(['administrador', 'cajero']);
    }

    public function sesionesCajaApertura(): HasMany
    {
        return $this->hasMany(SesionCaja::class, 'usuario_apertura_id');
    }

    public function sesionesCajaCierre(): HasMany
    {
        return $this->hasMany(SesionCaja::class, 'usuario_cierre_id');
    }

    public function eventosAuditoria(): HasMany
    {
        return $this->hasMany(EventoAuditoria::class, 'usuario_id');
    }

    public function establecimientos(): BelongsToMany
    {
        return $this->belongsToMany(
            Establecimiento::class,
            'establecimiento_usuario',
            'usuario_id',
            'establecimiento_id',
        )->withTimestamps();
    }

    public function canAccessEstablishment(Establecimiento|int $establecimiento): bool
    {
        if ($this->hasRole('administrador')) {
            return true;
        }

        $id = $establecimiento instanceof Establecimiento
            ? $establecimiento->getKey()
            : $establecimiento;

        return $this->establecimientos()->whereKey($id)->exists();
    }
}
