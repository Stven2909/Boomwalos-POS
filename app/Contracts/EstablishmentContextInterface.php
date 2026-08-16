<?php

namespace App\Contracts;

use App\Models\Establecimiento;
use Illuminate\Support\Collection;

/**
 * Contexto de sucursal activa para la sesión del operador.
 *
 * Reglas de comportamiento:
 * - `set()` falla con `AuthorizationException` si el usuario autenticado no
 *   puede acceder a la sucursal, y con `ValidationException` (clave
 *   `establecimiento`) si el usuario no tiene ninguna sucursal asignada.
 * - `id()` y `current()` lanzan `ValidationException` cuando no hay sucursal
 *   seleccionada; `idOrNull()` / `currentOrNull()` devuelven `null`.
 * - `idOrNull()` auto-selecciona la única sucursal accesible cuando el modo
 *   `single` no exige selección explícita
 *   (`config('tenancy.require_explicit_establishment') === false`).
 * - El contexto se persiste en `session('pos.establishment_id')` solo cuando
 *   la petición tiene sesión; en consola queda únicamente en el singleton.
 * - `ResolveTenant` (prependido al grupo `web`) resetea el contexto al
 *   finalizar cada request; no arrastrar contexto entre peticiones.
 */
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
