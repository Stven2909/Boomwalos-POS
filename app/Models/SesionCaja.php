<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SesionCaja extends Model
{
    protected $fillable = [
        'establecimiento_id',
        'usuario_apertura_id',
        'usuario_cierre_id',
        'monto_inicial',
        'efectivo_esperado',
        'efectivo_contado',
        'diferencia',
        'fecha_apertura',
        'fecha_cierre',
    ];

    protected function casts(): array
    {
        return [
            'monto_inicial' => 'decimal:2',
            'efectivo_esperado' => 'decimal:2',
            'efectivo_contado' => 'decimal:2',
            'diferencia' => 'decimal:2',
            'fecha_apertura' => 'datetime',
            'fecha_cierre' => 'datetime',
        ];
    }

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class);
    }

    public function usuarioApertura(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_apertura_id');
    }

    public function usuarioCierre(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_cierre_id');
    }
}
