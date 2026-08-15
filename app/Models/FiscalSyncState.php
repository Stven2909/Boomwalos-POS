<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalSyncState extends Model
{
    protected $fillable = [
        'establecimiento_id',
        'ultima_secuencia_webhook',
    ];

    protected function casts(): array
    {
        return [
            'ultima_secuencia_webhook' => 'integer',
        ];
    }

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class);
    }
}
