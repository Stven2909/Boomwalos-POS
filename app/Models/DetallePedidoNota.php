<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetallePedidoNota extends Model
{
    protected $fillable = [
        'detalle_pedido_id',
        'nota_cocina_id',
    ];

    public function detallePedido(): BelongsTo
    {
        return $this->belongsTo(DetallePedido::class);
    }

    public function notaCocina(): BelongsTo
    {
        return $this->belongsTo(NotaCocina::class);
    }
}
