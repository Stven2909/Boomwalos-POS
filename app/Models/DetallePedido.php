<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DetallePedido extends Model
{
    protected $table = 'detalles_pedido';

    protected $fillable = [
        'pedido_id',
        'tanda_id',
        'producto_id',
        'combo_id',
        'cantidad',
        'precio_unitario',
        'seleccion_combo',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'precio_unitario' => 'decimal:2',
            'seleccion_combo' => 'array',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function tanda(): BelongsTo
    {
        return $this->belongsTo(TandaPedido::class, 'tanda_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function combo(): BelongsTo
    {
        return $this->belongsTo(Combo::class);
    }

    public function detallePedidoNotas(): HasMany
    {
        return $this->hasMany(DetallePedidoNota::class);
    }

    public function notasCocina(): BelongsToMany
    {
        return $this->belongsToMany(NotaCocina::class, 'detalle_pedido_notas')
            ->using(DetallePedidoNota::class);
    }
}
