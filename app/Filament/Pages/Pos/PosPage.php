<?php

namespace App\Filament\Pages\Pos;

use App\Filament\Pages\Cash\OpenSession;
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
        if ($this->activeCashSession()) {
            return true;
        }

        $this->redirect(OpenSession::getUrl());

        return false;
    }

    protected function activeCashSession(): ?SesionCaja
    {
        $establishmentId = Establecimiento::query()->orderBy('id')->value('id');

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
        return Establecimiento::query()->orderBy('id')->firstOrFail();
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
