<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpcionComboProducto extends Model
{
    protected $table = 'opciones_combo_productos';

    protected $fillable = [
        'opcion_combo_id',
        'producto_id',
    ];

    public function opcionCombo(): BelongsTo
    {
        return $this->belongsTo(OpcionCombo::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
