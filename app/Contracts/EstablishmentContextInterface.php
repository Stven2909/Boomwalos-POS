<?php

namespace App\Contracts;

use App\Models\Establecimiento;
use Illuminate\Support\Collection;

interface EstablishmentContextInterface
{
    public function id(): int;

    public function idOrNull(): ?int;

    public function current(): Establecimiento;

    public function currentOrNull(): ?Establecimiento;

    public function set(int $establishmentId): Establecimiento;

    public function clear(): void;

    public function reset(): void;

    public function accessible(): Collection;

    public function canAccess(int $establishmentId): bool;
}
