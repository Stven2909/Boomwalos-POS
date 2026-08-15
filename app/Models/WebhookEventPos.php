<?php

namespace App\Models;

use App\Enums\EstadoWebhookPos;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookEventPos extends Model
{
    protected $table = 'webhook_events_pos';

    protected $fillable = [
        'establecimiento_id',
        'venta_fiscal_pos_id',
        'secuencia',
        'tipo',
        'payload',
        'estado',
        'recibido_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'estado' => EstadoWebhookPos::class,
            'secuencia' => 'integer',
            'recibido_at' => 'datetime',
        ];
    }

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class);
    }

    public function ventaFiscalPos(): BelongsTo
    {
        return $this->belongsTo(VentaFiscalPos::class);
    }
}
