<?php

namespace App\Models;

use App\Enums\TipoImpresora;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Impresora extends Model
{
    protected $fillable = [
        'nombre',
        'tipo',
        'configuracion',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoImpresora::class,
            'configuracion' => 'array',
        ];
    }

    public function trabajosImpresion(): HasMany
    {
        return $this->hasMany(TrabajoImpresion::class);
    }
}
