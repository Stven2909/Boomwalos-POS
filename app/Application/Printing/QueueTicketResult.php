<?php

namespace App\Application\Printing;

use App\Models\TrabajoImpresion;

final class QueueTicketResult
{
    public const CREATED = 'CREATED';

    public const NO_PRINTER = 'NO_PRINTER';

    public const FAILED = 'FAILED';

    private function __construct(
        public readonly string $status,
        public readonly ?TrabajoImpresion $trabajo,
        public readonly ?string $message,
    ) {
    }

    public static function created(TrabajoImpresion $trabajo): self
    {
        return new self(self::CREATED, $trabajo, null);
    }

    public static function noPrinter(): self
    {
        return new self(self::NO_PRINTER, null, 'Impresora de ticket no configurada.');
    }

    public static function failed(string $message): self
    {
        return new self(self::FAILED, null, $message);
    }

    public function succeeded(): bool
    {
        return $this->status === self::CREATED;
    }
}
