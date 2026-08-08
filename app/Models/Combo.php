<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Combo extends Model
{
    protected $fillable = [
        'nombre',
        'precio_fijo',
    ];

    protected function casts(): array
    {
        return [
            'precio_fijo' => 'decimal:2',
        ];
    }

    public function opcionesCombo(): HasMany
    {
        return $this->hasMany(OpcionCombo::class);
    }

    public function detallesPedido(): HasMany
    {
        return $this->hasMany(DetallePedido::class);
    }
}
