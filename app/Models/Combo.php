<?php

namespace App\Models;

use App\Enums\DisponibilidadProducto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Combo extends Model
{
    protected $fillable = [
        'nombre',
        'precio_fijo',
        'imagen_url',
        'disponibilidad',
    ];

    protected function casts(): array
    {
        return [
            'precio_fijo' => 'decimal:2',
            'disponibilidad' => DisponibilidadProducto::class,
        ];
    }

    public function opcionesCombo(): HasMany
    {
        return $this->hasMany(OpcionCombo::class);
    }

    public function detallesPedido(): HasMany
    {
        return $this->hasMany(DetallePedido::class);
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
