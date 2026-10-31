<?php

namespace App\Models;

use App\Enums\FlujoUbicacionMesa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class UbicacionMesa extends Model
{
    protected $table = 'ubicaciones_mesa';

    protected $fillable = [
        'establecimiento_id',
        'nombre',
        'flujo',
        'orden',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'flujo'  => FlujoUbicacionMesa::class,
            'activa' => 'boolean',
            'orden'  => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (UbicacionMesa $ubicacion): void {
            if (! $ubicacion->isDirty('flujo')) {
                return;
            }

            if ($ubicacion->flujo === FlujoUbicacionMesa::LLEVAR && $ubicacion->mesas()->exists()) {
                throw ValidationException::withMessages([
                    'flujo' => 'No puedes convertir en "Para llevar" una ubicación que todavía tiene mesas. Elimina o mueve sus mesas primero.',
                ]);
            }
        });

        static::updated(function (UbicacionMesa $ubicacion): void {
            if (! $ubicacion->wasChanged('nombre')) {
                return;
            }

            $nombreAnterior = (string) $ubicacion->getOriginal('nombre');

            if ($nombreAnterior === '' || $nombreAnterior === $ubicacion->nombre) {
                return;
            }

            Mesa::withTrashed()
                ->where('establecimiento_id', $ubicacion->establecimiento_id)
                ->where('zona', $nombreAnterior)
                ->update(['zona' => $ubicacion->nombre]);
        });
    }

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class);
    }

    /**
     * Mesas de esta ubicación dentro de la misma sucursal.
     *
     * No se declara como relación Eloquent porque la llave es el nombre de la
     * zona y Eloquent no permite condicionar por una columna del padre sin un
     * join. Al eager-loadear, la instancia padre no tiene atributos y el filtro
     * por establecimiento se perdería.
     */
    public function mesas(): Builder
    {
        return Mesa::query()
            ->where('establecimiento_id', $this->establecimiento_id)
            ->where('zona', $this->nombre);
    }

    public function esParaLlevar(): bool
    {
        return $this->flujo === FlujoUbicacionMesa::LLEVAR;
    }
}
