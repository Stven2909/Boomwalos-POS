<?php

namespace App\Models;

use App\Enums\EstadoColaVentaFiscal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ColaVentaFiscal extends Model
{
    protected $table = 'cola_ventas_fiscales';

    protected $fillable = [
        'venta_fiscal_pos_id',
        'clave_reintento',
        'payload_envio',
        'estado',
        'intentos',
        'ultimo_error',
    ];

    protected function casts(): array
    {
        return [
            'payload_envio' => 'array',
            'estado' => EstadoColaVentaFiscal::class,
            'intentos' => 'integer',
        ];
    }

    public function ventaFiscalPos(): BelongsTo
    {
        return $this->belongsTo(VentaFiscalPos::class);
    }
}
