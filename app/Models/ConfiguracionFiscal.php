<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfiguracionFiscal extends Model
{
    protected $table = 'configuraciones_fiscales';

    protected $fillable = [
        'establecimiento_id',
        'fiscal_habilitada',
        'cliente_key',
        'cliente_secret',
        'intentos_maximos',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_habilitada' => 'boolean',
            'cliente_secret' => 'encrypted',
            'intentos_maximos' => 'integer',
        ];
    }

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class);
    }
}
