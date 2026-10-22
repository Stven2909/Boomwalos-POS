<?php

namespace App\Models;

use App\Contracts\EstablishmentContextInterface;
use App\Enums\AreaPreparacion;
use App\Models\Concerns\BelongsToEstablecimiento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Categoria extends Model
{
    use BelongsToEstablecimiento;

    protected $fillable = [
        'establecimiento_id',
        'nombre',
        'descripcion',
        'activa',
        'parent_id',
        'icono',
        'area',
    ];

    protected static function booted(): void
    {
        static::creating(function (Categoria $categoria): void {
            if ($categoria->establecimiento_id !== null) {
                return;
            }

            $categoria->establecimiento_id = $categoria->parent?->establecimiento_id
                ?? app(EstablishmentContextInterface::class)->idOrNull();
        });

        static::saving(function (Categoria $categoria): void {
            if ($categoria->parent_id === null || $categoria->establecimiento_id === null) {
                return;
            }

            $parentEstablishmentId = self::query()
                ->whereKey($categoria->parent_id)
                ->value('establecimiento_id');

            if ($parentEstablishmentId !== null && (int) $parentEstablishmentId !== (int) $categoria->establecimiento_id) {
                throw ValidationException::withMessages([
                    'parent_id' => 'El grupo padre pertenece a otra sucursal.',
                ]);
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class);
    }

    public function isGroup(): bool
    {
        return $this->parent_id === null;
    }

    public function scopeGroups(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeCategories(Builder $query): Builder
    {
        return $query->whereNotNull('parent_id');
    }

    public function iconoType(): ?string
    {
        if (is_null($this->icono)) {
            return null;
        }

        $ext = strtolower(pathinfo($this->icono, PATHINFO_EXTENSION));

        return in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg']) ? 'image' : 'emoji';
    }

    public function iconoUrl(): ?string
    {
        if ($this->iconoType() !== 'image') {
            return null;
        }

        if (str_starts_with($this->icono, 'http')) {
            return $this->icono;
        }

        return asset('storage/' . ltrim($this->icono, '/'));
    }

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
            'area' => AreaPreparacion::class,
        ];
    }

    public function areaResuelta(): ?AreaPreparacion
    {
        if ($this->area instanceof AreaPreparacion) {
            return $this->area;
        }

        return $this->parent?->areaResuelta();
    }
}
