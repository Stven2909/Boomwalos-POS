<?php

namespace App\Application\Fiscal;

use RuntimeException;

class FiscalApiException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : "La API fiscal respondió con estado {$status}.", $status);
    }
}
