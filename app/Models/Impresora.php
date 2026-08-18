<?php

namespace App\Models;

use App\Contracts\EstablishmentContextInterface;
use App\Enums\TipoConexionImpresora;
use App\Enums\TipoImpresora;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Impresora extends Model
{
    protected $fillable = [
        'nombre',
        'tipo',
        'establecimiento_id',
        'conexion',
        'ip',
        'puerto',
        'dispositivo_usb',
        'activa',
        'configuracion',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoImpresora::class,
            'conexion' => TipoConexionImpresora::class,
            'configuracion' => 'array',
            'activa' => 'boolean',
            'puerto' => 'integer',
            'ultima_conexion_exitosa_at' => 'datetime',
        ];
    }

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class);
    }

    public function trabajosImpresion(): HasMany
    {
        return $this->hasMany(TrabajoImpresion::class);
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', true);
    }

    public function scopePorTipo(Builder $query, TipoImpresora $tipo): Builder
    {
        return $query->where('tipo', $tipo);
    }

    public static function buscar(TipoImpresora $tipo): ?self
    {
        $establecimientoId = app(EstablishmentContextInterface::class)->idOrNull();

        if ($establecimientoId) {
            $printer = static::activas()
                ->porTipo($tipo)
                ->where('establecimiento_id', $establecimientoId)
                ->first();

            if ($printer) {
                return $printer;
            }
        }

        return static::activas()
            ->porTipo($tipo)
            ->whereNull('establecimiento_id')
            ->first();
    }

    public function direccionConexion(): string
    {
        return $this->conexion === TipoConexionImpresora::RED
            ? "{$this->ip}:{$this->puerto}"
            : ($this->dispositivo_usb ?? 'No configurado');
    }
}
