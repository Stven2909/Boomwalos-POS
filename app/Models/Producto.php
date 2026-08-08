<?php

namespace App\Models;

use App\Enums\DisponibilidadProducto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    protected $fillable = [
        'categoria_id',
        'nombre',
        'precio',
        'imagen_url',
        'disponibilidad',
    ];

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'disponibilidad' => DisponibilidadProducto::class,
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function detallesPedido(): HasMany
    {
        return $this->hasMany(DetallePedido::class);
    }

    public function opcionesComboProductos(): HasMany
    {
        return $this->hasMany(OpcionComboProducto::class);
    }
}
