<?php

namespace App\Filament\Pages;

use App\Contracts\EstablishmentContextInterface;
use App\Filament\Pages\Cash\OpenSession;
use App\Filament\Pages\Pos\ServiceSelection;
use App\Models\SesionCaja;
use Filament\Pages\Page;

class EstablishmentSelection extends Page
{
    protected static ?string $slug = 'seleccionar-sucursal';

    protected static ?string $title = 'Seleccionar sucursal';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.admin.pages.establishment-selection';

    public static function canAccess(): bool
    {
        return auth()->check()
            && app(EstablishmentContextInterface::class)->accessible()->count() > 1;
    }

    public function establishments(): mixed
    {
        return app(EstablishmentContextInterface::class)->accessible();
    }

    public function select(int $establishmentId): void
    {
        $context = app(EstablishmentContextInterface::class);
        $context->set($establishmentId);

        $hasActiveSession = SesionCaja::query()
            ->where('establecimiento_id', $establishmentId)
            ->whereNull('fecha_cierre')
            ->exists();

        $this->redirect($hasActiveSession ? ServiceSelection::getUrl() : OpenSession::getUrl());
    }
}
