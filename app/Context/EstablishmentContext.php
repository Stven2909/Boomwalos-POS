<?php

namespace App\Context;

use App\Contracts\EstablishmentContextInterface;
use App\Models\Establecimiento;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class EstablishmentContext implements EstablishmentContextInterface
{
    private ?int $establishmentId = null;

    private ?Establecimiento $establishment = null;

    public function id(): int
    {
        return $this->idOrNull() ?? throw ValidationException::withMessages([
            'establecimiento' => 'Selecciona la sucursal en la que deseas trabajar.',
        ]);
    }

    public function idOrNull(): ?int
    {
        if ($this->establishmentId !== null) {
            return $this->establishmentId;
        }

        $sessionId = $this->sessionId();

        if ($sessionId !== null && $this->canAccess($sessionId)) {
            $this->establishmentId = $sessionId;

            return $sessionId;
        }

        $available = $this->accessible();

        if ($available->count() === 1) {
            return $this->set((int) $available->first()->getKey())->getKey();
        }

        return null;
    }

    public function current(): Establecimiento
    {
        if ($this->establishment !== null && $this->establishment->getKey() === $this->id()) {
            return $this->establishment;
        }

        return $this->establishment = Establecimiento::query()->findOrFail($this->id());
    }

    public function currentOrNull(): ?Establecimiento
    {
        if ($this->establishment !== null && $this->establishment->getKey() === $this->idOrNull()) {
            return $this->establishment;
        }

        $establishmentId = $this->idOrNull();

        if ($establishmentId === null) {
            $this->establishment = null;

            return null;
        }

        return $this->establishment = Establecimiento::query()->find($establishmentId);
    }

    public function set(int $establishmentId): Establecimiento
    {
        $establishment = Establecimiento::query()->find($establishmentId);

        if (! $establishment) {
            throw ValidationException::withMessages([
                'establecimiento' => 'La sucursal seleccionada no existe.',
            ]);
        }

        if (! $this->canAccess($establishmentId)) {
            throw new AuthorizationException('No tienes acceso a esta sucursal.');
        }

        $this->establishmentId = $establishmentId;
        $this->establishment = $establishment;

        if ($this->hasSession()) {
            request()->session()->put('pos.establishment_id', $establishmentId);
        }

        return $establishment;
    }

    public function clear(): void
    {
        $this->reset();

        if ($this->hasSession()) {
            request()->session()->forget('pos.establishment_id');
        }
    }

    public function reset(): void
    {
        $this->establishmentId = null;
        $this->establishment = null;
    }

    public function accessible(): Collection
    {
        $user = auth()->user();

        if (! $user instanceof User || $user->hasRole('administrador')) {
            return Establecimiento::query()->orderBy('id')->get();
        }

        $assigned = $user->establecimientos()->orderBy('establecimientos.id')->get();

        if ($assigned->isEmpty() && ! config('tenancy.require_explicit_establishment', false) && Establecimiento::query()->count() === 1) {
            return Establecimiento::query()->orderBy('id')->get();
        }

        return $assigned;
    }

    public function canAccess(int $establishmentId): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return Establecimiento::query()->whereKey($establishmentId)->exists();
        }

        if ($user->hasRole('administrador')) {
            return Establecimiento::query()->whereKey($establishmentId)->exists();
        }

        if ($user->establecimientos()->whereKey($establishmentId)->exists()) {
            return true;
        }

        // Compatibility for the existing single-tenant installation. New
        // production tenants must assign every operator to a branch.
        return ! config('tenancy.require_explicit_establishment', false)
            && Establecimiento::query()->count() === 1
            && Establecimiento::query()->whereKey($establishmentId)->exists();
    }

    private function sessionId(): ?int
    {
        if (! $this->hasSession()) {
            return null;
        }

        $id = request()->session()->get('pos.establishment_id');

        return is_numeric($id) ? (int) $id : null;
    }

    private function hasSession(): bool
    {
        return ! app()->runningInConsole()
            && request()->hasSession();
    }
}
