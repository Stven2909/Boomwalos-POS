<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    protected $fillable = [
        'nombre',
        'descripcion',
        'activa',
    ];

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class);
    }

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }
}
