<?php

namespace App\Models;

use App\Enums\EstadoImpresion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrabajoImpresion extends Model
{
    protected $table = 'trabajo_impresion';

    protected $fillable = [
        'impresora_id',
        'tanda_id',
        'pedido_id',
        'estado',
        'contenido',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoImpresion::class,
        ];
    }

    public function impresora(): BelongsTo
    {
        return $this->belongsTo(Impresora::class);
    }

    public function tanda(): BelongsTo
    {
        return $this->belongsTo(TandaPedido::class, 'tanda_id');
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }
}
