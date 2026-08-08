<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OpcionCombo extends Model
{
    protected $table = 'opciones_combo';

    protected $fillable = [
        'combo_id',
        'nombre',
        'cantidad_requerida',
        'es_obligatorio',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_requerida' => 'integer',
            'es_obligatorio' => 'boolean',
        ];
    }

    public function combo(): BelongsTo
    {
        return $this->belongsTo(Combo::class);
    }

    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'opciones_combo_productos')
            ->using(OpcionComboProducto::class);
    }
}
