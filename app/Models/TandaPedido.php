<?php

namespace App\Models;

use App\Enums\EstadoCocina;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TandaPedido extends Model
{
    protected $table = 'tandas_pedido';

    protected $fillable = [
        'pedido_id',
        'numero_tanda',
        'estado_cocina',
    ];

    protected function casts(): array
    {
        return [
            'numero_tanda' => 'integer',
            'estado_cocina' => EstadoCocina::class,
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetallePedido::class, 'tanda_id');
    }

    public function trabajosImpresion(): HasMany
    {
        return $this->hasMany(TrabajoImpresion::class, 'tanda_id');
    }
}
