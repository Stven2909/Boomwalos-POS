<?php

namespace App\Filament\Pages;

use App\Contracts\EstablishmentContextInterface;
use App\Models\SesionCaja;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    public function getOperationContextProperty(): string
    {
        $context = app(EstablishmentContextInterface::class);
        $establishment = $context->currentOrNull();
        $session = $establishment
            ? SesionCaja::query()
                ->where('establecimiento_id', $establishment->getKey())
                ->whereNull('fecha_cierre')
                ->latest('id')
                ->first()
            : null;

        return mb_strtoupper($establishment?->nombre ?? 'SUCURSAL SIN SELECCIONAR')
            .($session ? ' · TURNO #'.$session->getKey() : ' · SIN TURNO');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.admin.pages.dashboard'),
            ]);
    }
}
