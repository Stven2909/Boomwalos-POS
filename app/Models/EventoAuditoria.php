<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EventoAuditoria extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'entidad_tipo',
        'entidad_id',
        'usuario_id',
        'tipo_evento',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function entidad(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'entidad_tipo', 'entidad_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
