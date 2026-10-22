<?php

namespace App\Models;

use App\Contracts\EstablishmentContextInterface;
use App\Enums\DisponibilidadProducto;
use App\Models\Concerns\BelongsToEstablecimiento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class Producto extends Model
{
    use BelongsToEstablecimiento;

    protected $fillable = [
        'establecimiento_id',
        'categoria_id',
        'nombre',
        'precio',
        'imagen_url',
        'disponibilidad',
        'requiere_masa',
    ];

    protected static function booted(): void
    {
        static::creating(function (Producto $producto): void {
            if ($producto->establecimiento_id !== null) {
                return;
            }

            $producto->establecimiento_id = $producto->categoria?->establecimiento_id
                ?? app(EstablishmentContextInterface::class)->idOrNull();
        });

        static::saving(function (Producto $producto): void {
            if ($producto->categoria_id === null || $producto->establecimiento_id === null) {
                return;
            }

            $categoryEstablishmentId = Categoria::query()
                ->whereKey($producto->categoria_id)
                ->value('establecimiento_id');

            if ($categoryEstablishmentId !== null && (int) $categoryEstablishmentId !== (int) $producto->establecimiento_id) {
                throw ValidationException::withMessages([
                    'categoria_id' => 'La categoría pertenece a otra sucursal.',
                ]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'disponibilidad' => DisponibilidadProducto::class,
            'requiere_masa' => 'boolean',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function detallesPedido(): HasMany
    {
        return $this->hasMany(DetallePedido::class);
    }

    public function opcionesComboProductos(): HasMany
    {
        return $this->hasMany(OpcionComboProducto::class);
    }

    public function imageUrl(): ?string
    {
        if (! $this->imagen_url) {
            return null;
        }

        if (str_starts_with($this->imagen_url, 'http://') || str_starts_with($this->imagen_url, 'https://')) {
            $parsed = parse_url($this->imagen_url);

            if (in_array($parsed['host'] ?? null, ['localhost', '127.0.0.1'], true) && str_starts_with($parsed['path'] ?? '', '/storage/')) {
                $path = ltrim(substr($parsed['path'], strlen('/storage/')), '/');
                $disk = Storage::disk('public');

                return $disk->exists($path) ? $disk->url($path) : null;
            }

            return $this->imagen_url;
        }

        if (str_starts_with($this->imagen_url, '/storage/')) {
            $path = ltrim(substr($this->imagen_url, strlen('/storage/')), '/');
            $disk = Storage::disk('public');

            return $disk->exists($path) ? $disk->url($path) : null;
        }

        if (str_starts_with($this->imagen_url, '/')) {
            return $this->imagen_url;
        }

        $disk = Storage::disk('public');

        return $disk->exists($this->imagen_url)
            ? $disk->url($this->imagen_url)
            : null;
    }
}
