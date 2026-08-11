<?php

namespace App\Models;

use App\Enums\EstadoComercialPedido;
use App\Enums\OrigenPedido;
use App\Enums\TipoPedido;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Pedido extends Model
{
    protected $fillable = [
        'numero_seguimiento',
        'tipo_pedido',
        'mesa_id',
        'establecimiento_id',
        'usuario_id',
        'origen_pedido',
        'codigo_corto',
        'fecha_codigo',
        'estado_comercial',
    ];

    protected function casts(): array
    {
        return [
            'tipo_pedido' => TipoPedido::class,
            'origen_pedido' => OrigenPedido::class,
            'estado_comercial' => EstadoComercialPedido::class,
            'codigo_corto' => 'integer',
            'fecha_codigo' => 'date',
        ];
    }

    public function mesa(): BelongsTo
    {
        return $this->belongsTo(Mesa::class);
    }

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function tandas(): HasMany
    {
        return $this->hasMany(TandaPedido::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetallePedido::class);
    }

    public function pago(): HasOne
    {
        return $this->hasOne(Pago::class);
    }

    public function trabajosImpresion(): HasMany
    {
        return $this->hasMany(TrabajoImpresion::class);
    }

    public function documentosFiscales(): HasMany
    {
        return $this->hasMany(DocumentoFiscal::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->estado_comercial, [
            EstadoComercialPedido::ABIERTO,
            EstadoComercialPedido::PENDIENTE_COBRO,
            EstadoComercialPedido::COBRADO,
        ], true);
    }

    public function isUnpaid(): bool
    {
        return in_array($this->estado_comercial, [
            EstadoComercialPedido::ABIERTO,
            EstadoComercialPedido::PENDIENTE_COBRO,
        ], true);
    }

    public function codigoCortoLabel(): string
    {
        return $this->codigo_corto !== null ? '#'.$this->codigo_corto : '';
    }

    public function total(): float
    {
        if ($this->relationLoaded('detalles')) {
            return (float) $this->detalles
                ->where('estado_linea', \App\Enums\EstadoLineaPedido::ACTIVA)
                ->sum(fn (DetallePedido $detalle): float => (float) $detalle->precio_unitario * $detalle->cantidad);
        }

        return (float) $this->detalles()
            ->where('estado_linea', \App\Enums\EstadoLineaPedido::ACTIVA)
            ->sum(DB::raw('cantidad * precio_unitario'));
    }
}
