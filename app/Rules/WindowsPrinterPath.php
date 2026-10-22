<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

class WindowsPrinterPath implements ValidationRule
{
    public static function toSmb(string $path): string
    {
        $parts = str_starts_with($path, '\\\\') ? explode('\\', substr($path, 2)) : [];
        $destination = count($parts) === 2 ? 'smb://'.implode('/', $parts) : '';

        if (strlen($path) > 255 || str_contains($path, '/') || preg_match('/[\x00-\x1f\x7f]/', $path) || $destination === '' || preg_match(WindowsPrintConnector::REGEX_SMB, $destination) !== 1 || str_contains($destination, '@')) {
            throw new InvalidArgumentException('Usa una ruta compartida con servidor e impresora, por ejemplo \\\\CAJA-PC\\RECIBOS, sin credenciales ni carpetas adicionales.');
        }

        return $destination;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            self::toSmb(is_string($value) ? $value : '');
        } catch (InvalidArgumentException $exception) {
            $fail($exception->getMessage());
        }
    }
}
