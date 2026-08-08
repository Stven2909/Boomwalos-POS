<?php

namespace App\Models;

use App\Enums\EstadoComercialPedido;
use App\Enums\TipoPedido;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Pedido extends Model
{
    protected $fillable = [
        'numero_seguimiento',
        'tipo_pedido',
        'mesa_id',
        'establecimiento_id',
        'usuario_id',
        'estado_comercial',
    ];

    protected function casts(): array
    {
        return [
            'tipo_pedido' => TipoPedido::class,
            'estado_comercial' => EstadoComercialPedido::class,
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
}
