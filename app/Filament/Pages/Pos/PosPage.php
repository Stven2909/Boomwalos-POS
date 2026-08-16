<?php

namespace App\Filament\Pages\Pos;

use App\Filament\Pages\Cash\OpenSession;
use App\Filament\Pages\EstablishmentSelection;
use App\Models\Establecimiento;
use App\Models\SesionCaja;
use Filament\Pages\Page;

abstract class PosPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('crear_pedido');
    }

    protected function ensureCashSession(): bool
    {
        $context = app(\App\Contracts\EstablishmentContextInterface::class);

        if ($context->idOrNull() === null) {
            if ($context->accessible()->count() > 1) {
                $this->redirect(EstablishmentSelection::getUrl());
            } else {
                abort(403, 'No tienes acceso a ninguna sucursal.');
            }

            return false;
        }

        if ($this->activeCashSession()) {
            return true;
        }

        $this->redirect(OpenSession::getUrl());

        return false;
    }

    protected function activeCashSession(): ?SesionCaja
    {
        $establishmentId = app(\App\Contracts\EstablishmentContextInterface::class)->idOrNull();

        if (! $establishmentId) {
            return null;
        }

        return SesionCaja::query()
            ->where('establecimiento_id', $establishmentId)
            ->whereNull('fecha_cierre')
            ->latest('id')
            ->first();
    }

    protected function establishment(): Establecimiento
    {
        return app(\App\Contracts\EstablishmentContextInterface::class)->current();
    }

    protected function establishmentOrNull(): ?Establecimiento
    {
        return app(\App\Contracts\EstablishmentContextInterface::class)->currentOrNull();
    }

    protected function actorName(): string
    {
        return mb_strtoupper(auth()->user()?->getFilamentName() ?? 'USUARIO');
    }

    protected function money(float|int|string $amount): string
    {
        return '$' . number_format((float) $amount, 2, '.', ',');
    }
}
