<?php

namespace App\Services;

use App\Contracts\EstablishmentContextInterface;
use App\Enums\FlujoPos;
use App\Enums\TipoPedido;
use App\Models\Configuracion;
use App\ValueObjects\ConfiguracionFlujosPos;
use Illuminate\Validation\ValidationException;

final class PoliticaFlujosPos
{
    public const CONFIG_KEY = 'pos.flujos_operativos';

    public function __construct(
        private readonly EstablishmentContextInterface $establishmentContext,
        private readonly ConfiguracionService $configuracionService,
    ) {}

    public function actual(bool $fresh = false): ConfiguracionFlujosPos
    {
        $raw = $fresh
            ? Configuracion::query()
                ->where('establecimiento_id', $this->establishmentContext->id())
                ->where('clave', self::CONFIG_KEY)
                ->value('valor')
            : $this->configuracionService->get(self::CONFIG_KEY);

        if ($raw === null) {
            return ConfiguracionFlujosPos::defaults();
        }

        try {
            return ConfiguracionFlujosPos::fromArray(is_array($raw) ? $raw : []);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'flujos' => 'La configuración operativa de esta sucursal es inválida. Corrígela desde TI. '.$exception->getMessage(),
            ]);
        }
    }

    public function permite(FlujoPos $flujo, bool $fresh = false): bool
    {
        return $this->actual($fresh)->permite($flujo);
    }

    public function permiteTipo(TipoPedido $tipoPedido, bool $fresh = false): bool
    {
        return $this->permite(FlujoPos::fromTipoPedido($tipoPedido), $fresh);
    }

    public function assertPuedeIniciar(TipoPedido $tipoPedido, bool $fresh = false): void
    {
        $flujo = FlujoPos::fromTipoPedido($tipoPedido);

        if (! $this->permite($flujo, $fresh)) {
            throw ValidationException::withMessages([
                'flujo' => "El flujo {$flujo->label()} está deshabilitado para esta sucursal.",
            ]);
        }
    }
}
