<?php

namespace App\Models;

use App\Enums\EstadoVentaFiscal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VentaFiscalPos extends Model
{
    protected $table = 'ventas_fiscales_pos';

    protected $fillable = [
        'establecimiento_id',
        'pedido_id',
        'pago_id',
        'referencia',
        'monto_total',
        'metodo_pago',
        'receptor',
        'fiscal_sale_id',
        'estado',
        'sincronizado_at',
    ];

    protected function casts(): array
    {
        return [
            'monto_total' => 'decimal:2',
            'receptor' => 'array',
            'estado' => EstadoVentaFiscal::class,
            'sincronizado_at' => 'datetime',
        ];
    }

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class);
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    public function cola(): HasOne
    {
        return $this->hasOne(ColaVentaFiscal::class);
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoFiscal::class);
    }
}
