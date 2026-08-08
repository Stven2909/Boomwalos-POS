<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
