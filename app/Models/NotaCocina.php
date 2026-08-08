<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotaCocina extends Model
{
    protected $table = 'notas_cocina';

    protected $fillable = [
        'nombre',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function detallePedidoNotas(): HasMany
    {
        return $this->hasMany(DetallePedidoNota::class);
    }

    public function detallesPedido(): BelongsToMany
    {
        return $this->belongsToMany(DetallePedido::class, 'detalle_pedido_notas')
            ->using(DetallePedidoNota::class);
    }
}
