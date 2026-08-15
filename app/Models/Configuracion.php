<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Configuracion extends Model
{
    protected $table = 'configuraciones';

    protected $fillable = [
        'establecimiento_id',
        'clave',
        'valor',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'array',
        ];
    }

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class);
    }
}
