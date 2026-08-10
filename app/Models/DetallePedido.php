<?php

namespace App\Models;

use App\Enums\EstadoLineaPedido;
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
        'estado_linea',
        'cancelada_por_id',
        'cancelada_at',
        'motivo_cancelacion',
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
            'estado_linea' => EstadoLineaPedido::class,
            'cancelada_at' => 'datetime',
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

    public function canceladaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelada_por_id');
    }

    public function isPending(): bool
    {
        return $this->tanda_id === null && $this->estado_linea === EstadoLineaPedido::ACTIVA;
    }

    public function isActive(): bool
    {
        return $this->estado_linea === EstadoLineaPedido::ACTIVA;
    }
}
