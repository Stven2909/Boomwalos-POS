<?php

namespace App\Models;

use App\Enums\MetodoPago;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pago extends Model
{
    protected $fillable = [
        'pedido_id',
        'metodo_pago',
        'monto_recibido',
        'cambio_devuelto',
    ];

    protected function casts(): array
    {
        return [
            'metodo_pago' => MetodoPago::class,
            'monto_recibido' => 'decimal:2',
            'cambio_devuelto' => 'decimal:2',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }
}
