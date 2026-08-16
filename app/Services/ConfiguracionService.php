<?php

namespace App\Services;

use App\Contracts\EstablishmentContextInterface;
use App\Models\Configuracion;
use Illuminate\Support\Facades\Cache;

class ConfiguracionService
{
    public function __construct(private readonly EstablishmentContextInterface $establishmentContext) {}

    private const TIPOS = [
        'pos.montos_rapidos_efectivo' => 'array',
        'moneda.simbolo' => 'string',
        'impresion.ticket_activo' => 'boolean',
    ];

    public function get(string $clave, mixed $default = null): mixed
    {
        $establecimientoId = $this->establishmentId();
        $cacheKey = $this->cacheKey($establecimientoId, $clave);

        $cached = Cache::get($cacheKey, new \stdClass());

        if (! $cached instanceof \stdClass) {
            return $cached;
        }

        $valor = Configuracion::query()
            ->where('establecimiento_id', $establecimientoId)
            ->where('clave', $clave)
            ->value('valor');

        $resolved = $valor !== null ? $valor : $default;

        Cache::put($cacheKey, $resolved, now()->addDay());

        return $resolved;
    }

    public function set(string $clave, mixed $valor): void
    {
        $this->validate($clave, $valor);

        $establecimientoId = $this->establishmentId();

        Configuracion::query()->updateOrCreate(
            ['establecimiento_id' => $establecimientoId, 'clave' => $clave],
            ['valor' => $valor],
        );

        Cache::forget($this->cacheKey($establecimientoId, $clave));
    }

    public function has(string $clave): bool
    {
        $establecimientoId = $this->establishmentId();

        return Configuracion::query()
            ->where('establecimiento_id', $establecimientoId)
            ->where('clave', $clave)
            ->exists();
    }

    private function validate(string $clave, mixed $valor): void
    {
        $tipoEsperado = self::TIPOS[$clave] ?? null;

        if ($tipoEsperado === null) {
            return;
        }

        $coincide = match ($tipoEsperado) {
            'array' => is_array($valor),
            'string' => is_string($valor),
            'boolean' => is_bool($valor),
            'integer' => is_int($valor),
            'number' => is_int($valor) || is_float($valor),
            default => true,
        };

        if (! $coincide) {
            throw new \InvalidArgumentException(
                "La configuración [{$clave}] debe ser de tipo {$tipoEsperado}.",
            );
        }
    }

    private function cacheKey(int $establecimientoId, string $clave): string
    {
        return "configuracion:{$establecimientoId}:{$clave}";
    }

    private function establishmentId(): int
    {
        return $this->establishmentContext->id();
    }
}
