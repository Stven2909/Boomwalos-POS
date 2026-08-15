<?php

namespace App\Models;

use App\Enums\EstadoImpresion;
use App\Enums\TipoTrabajoImpresion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrabajoImpresion extends Model
{
    protected $table = 'trabajo_impresion';

    protected $fillable = [
        'impresora_id',
        'tanda_id',
        'pedido_id',
        'tipo_trabajo',
        'es_reimpresion',
        'reimpresion_de_id',
        'motivo_reimpresion',
        'usuario_reimpresion_id',
        'original_uid',
        'estado',
        'contenido',
    ];

    protected function casts(): array
    {
        return [
            'es_reimpresion' => 'boolean',
            'tipo_trabajo' => TipoTrabajoImpresion::class,
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

    public function reimpresionDe(): BelongsTo
    {
        return $this->belongsTo(TrabajoImpresion::class, 'reimpresion_de_id');
    }

    public function usuarioReimpresion(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_reimpresion_id');
    }

    public function isTicket(): bool
    {
        return $this->tipo_trabajo === TipoTrabajoImpresion::TICKET;
    }
}
