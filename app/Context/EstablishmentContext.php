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

    private ?int $userId = null;

    public function id(): int
    {
        return $this->idOrNull() ?? throw ValidationException::withMessages([
            'establecimiento' => 'Selecciona la sucursal en la que deseas trabajar.',
        ]);
    }

    public function idOrNull(): ?int
    {
        $this->refreshUserScope();

        if ($this->establishmentId !== null && ! $this->canAccess($this->establishmentId)) {
            $this->establishmentId = null;
            $this->establishment = null;
        }

        if ($this->establishmentId !== null) {
            return $this->establishmentId;
        }

        $sessionId = $this->sessionId();

        if ($sessionId !== null) {
            if ($this->canAccess($sessionId)) {
                $this->establishmentId = $sessionId;

                return $sessionId;
            }

            $this->clear();
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
        $this->refreshUserScope();

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
        $this->userId = auth()->id();
    }

    public function accessible(): Collection
    {
        $this->refreshUserScope();

        $user = auth()->user();

        if (! $user instanceof User || $user->hasRole('administrador')) {
            return Establecimiento::query()->orderBy('id')->get();
        }

        $assigned = $user->establecimientos()->orderBy('establecimientos.id')->get();

        if ($assigned->isEmpty() && ! config('pos.require_explicit_establishment', false) && Establecimiento::query()->count() === 1) {
            return Establecimiento::query()->orderBy('id')->get();
        }

        return $assigned;
    }

    public function canAccess(int $establishmentId): bool
    {
        $this->refreshUserScope();

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
        return ! config('pos.require_explicit_establishment', false)
            && Establecimiento::query()->count() === 1
            && Establecimiento::query()->whereKey($establishmentId)->exists();
    }

    private function refreshUserScope(): void
    {
        $userId = auth()->id();

        if ($this->userId !== null && $userId !== $this->userId) {
            $this->establishmentId = null;
            $this->establishment = null;
        }

        $this->userId = $userId;
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
