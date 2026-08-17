<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Categoria extends Model
{
    protected $fillable = [
        'nombre',
        'descripcion',
        'activa',
        'parent_id',
        'icono',
    ];

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
        ];
    }
}
