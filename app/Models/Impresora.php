<?php

namespace App\Models;

use App\Contracts\EstablishmentContextInterface;
use App\Enums\AreaPreparacion;
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
        'area',
        'establecimiento_id',
        'conexion',
        'ip',
        'puerto',
        'dispositivo_usb',
        'ruta_windows',
        'activa',
        'configuracion',
        'ultima_conexion_exitosa_at',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoImpresora::class,
            'area' => AreaPreparacion::class,
            'conexion' => TipoConexionImpresora::class,
            'configuracion' => 'array',
            'activa' => 'boolean',
            'puerto' => 'integer',
            'ultima_conexion_exitosa_at' => 'datetime',
        ];
    }

    public function setPuertoAttribute(mixed $value): void
    {
        $this->attributes['puerto'] = filled($value) ? (int) $value : 9100;
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

    public function scopePorArea(Builder $query, ?AreaPreparacion $area): Builder
    {
        return $area === null
            ? $query->whereNull('area')
            : $query->where('area', $area->value);
    }

    public static function buscar(TipoImpresora $tipo, ?int $establecimientoId = null): ?self
    {
        $establecimientoId ??= app(EstablishmentContextInterface::class)->idOrNull();

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

    public static function buscarPorArea(TipoImpresora $tipo, ?AreaPreparacion $area, ?int $establecimientoId = null): ?self
    {
        $establecimientoId ??= app(EstablishmentContextInterface::class)->idOrNull();

        if ($establecimientoId === null) {
            return null;
        }

        $query = static::activas()
            ->porTipo($tipo)
            ->where('establecimiento_id', $establecimientoId)
            ->orderBy('id');

        if ($area !== null) {
            $printer = (clone $query)->where('area', $area->value)->first();

            if ($printer) {
                return $printer;
            }
        }

        $printer = (clone $query)->whereNull('area')->first();

        if ($printer) {
            return $printer;
        }

        return (clone $query)->first();
    }

    public function direccionConexion(): string
    {
        return match ($this->conexion) {
            TipoConexionImpresora::RED => "{$this->ip}:{$this->puerto}",
            TipoConexionImpresora::USB => ($this->dispositivo_usb ?? 'No configurado'),
            TipoConexionImpresora::WINDOWS => ($this->ruta_windows ?? 'No configurado'),
            TipoConexionImpresora::PDF => 'Simulador Virtual (PDF)',
            default => 'No configurado',
        };
    }
}
