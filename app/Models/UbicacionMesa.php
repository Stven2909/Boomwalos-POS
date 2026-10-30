<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UbicacionMesa extends Model
{
    protected $table = 'ubicaciones_mesa';

    protected $fillable = [
        'establecimiento_id',
        'nombre',
        'orden',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
            'orden'  => 'integer',
        ];
    }

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class);
    }

    public function mesas(): HasMany
    {
        return $this->hasMany(Mesa::class, 'zona', 'nombre')
            ->where('mesas.establecimiento_id', $this->establecimiento_id);
    }
}
