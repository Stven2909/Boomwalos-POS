<?php

namespace App\Models;

use App\Enums\EstadoMesa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mesa extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'establecimiento_id',
        'numero',
        'zona',
        'estado',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoMesa::class,
            // 'zona' es texto libre con el nombre de la ubicación actual.
            'activa' => 'boolean',
        ];
    }

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class);
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }
}
