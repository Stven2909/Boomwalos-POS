<?php

namespace App\Models\Concerns;

use App\Contracts\EstablishmentContextInterface;
use App\Models\Establecimiento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToEstablecimiento
{
    public static function bootBelongsToEstablecimiento(): void
    {
        static::creating(function ($model): void {
            if ($model->getAttribute('establecimiento_id') !== null) {
                return;
            }

            $model->setAttribute(
                'establecimiento_id',
                app(EstablishmentContextInterface::class)->idOrNull(),
            );
        });
    }

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class);
    }

    public function scopePorEstablecimiento(Builder $query, int|Establecimiento|null $establecimiento): Builder
    {
        if ($establecimiento === null) {
            return $query;
        }

        return $query->where(
            'establecimiento_id',
            $establecimiento instanceof Establecimiento
                ? $establecimiento->getKey()
                : $establecimiento,
        );
    }
}
