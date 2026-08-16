<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Establecimiento extends Model
{
    protected $fillable = [
        'nombre',
        'direccion',
        'codigo_establecimiento',
        'codigo_punto_venta',
    ];

    public function mesas(): HasMany
    {
        return $this->hasMany(Mesa::class);
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }

    public function sesionesCaja(): HasMany
    {
        return $this->hasMany(SesionCaja::class);
    }

    public function configuracionFiscal(): HasOne
    {
        return $this->hasOne(ConfiguracionFiscal::class);
    }

    public function fiscalSyncState(): HasOne
    {
        return $this->hasOne(FiscalSyncState::class);
    }

    public function ventasFiscalesPos(): HasMany
    {
        return $this->hasMany(VentaFiscalPos::class);
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'establecimiento_usuario',
            'establecimiento_id',
            'usuario_id',
        )->withTimestamps();
    }
}
