<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalSnapshotOutbox extends Model
{
    protected $table = 'portal_snapshot_outbox';

    protected $fillable = [
        'pedido_id',
        'establecimiento_id',
        'tracking',
        'payload',
        'estado',
        'intentos',
        'proximo_intento_at',
        'bloqueado_at',
        'sincronizado_at',
        'ultimo_error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'intentos' => 'integer',
            'proximo_intento_at' => 'datetime',
            'bloqueado_at' => 'datetime',
            'sincronizado_at' => 'datetime',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }
}
