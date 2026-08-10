<?php

namespace App\Models;

use App\Enums\EstadoMesa;
use App\Enums\ZonaMesa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mesa extends Model
{
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
            'zona' => ZonaMesa::class,
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
