<?php

namespace App\ValueObjects;

use App\Enums\FlujoPos;
use InvalidArgumentException;

final readonly class ConfiguracionFlujosPos
{
    public const VERSION = 1;

    public function __construct(
        public bool $mostradorPrepago,
        public bool $mesaPostpago,
        public FlujoPos $predeterminado,
    ) {
        if (! $mostradorPrepago && ! $mesaPostpago) {
            throw new InvalidArgumentException('Debe permanecer habilitado al menos un flujo del POS.');
        }

        if (! $this->permite($predeterminado)) {
            throw new InvalidArgumentException('El flujo predeterminado también debe estar habilitado.');
        }
    }

    public static function defaults(): self
    {
        return new self(true, true, FlujoPos::MOSTRADOR_PREPAGO);
    }

    public static function fromArray(array $value): self
    {
        $allowed = ['version', 'mostrador_prepago', 'mesa_postpago', 'predeterminado'];
        $unknown = array_diff(array_keys($value), $allowed);

        if ($unknown !== []) {
            throw new InvalidArgumentException('La configuración contiene campos desconocidos: '.implode(', ', $unknown).'.');
        }

        if (($value['version'] ?? null) !== self::VERSION) {
            throw new InvalidArgumentException('La versión de configuración de flujos no es compatible.');
        }

        if (! is_bool($value['mostrador_prepago'] ?? null) || ! is_bool($value['mesa_postpago'] ?? null)) {
            throw new InvalidArgumentException('Los estados de los flujos deben ser valores booleanos.');
        }

        $default = FlujoPos::tryFrom((string) ($value['predeterminado'] ?? ''));

        if (! $default) {
            throw new InvalidArgumentException('Selecciona un flujo predeterminado válido.');
        }

        return new self(
            $value['mostrador_prepago'],
            $value['mesa_postpago'],
            $default,
        );
    }

    public function permite(FlujoPos $flujo): bool
    {
        return match ($flujo) {
            FlujoPos::MOSTRADOR_PREPAGO => $this->mostradorPrepago,
            FlujoPos::MESA_POSTPAGO => $this->mesaPostpago,
        };
    }

    public function toArray(): array
    {
        return [
            'version' => self::VERSION,
            'mostrador_prepago' => $this->mostradorPrepago,
            'mesa_postpago' => $this->mesaPostpago,
            'predeterminado' => $this->predeterminado->value,
        ];
    }
}
