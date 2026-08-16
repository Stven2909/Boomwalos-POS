<?php

namespace App\Filament\Resources\Establecimientos\Pages;

use App\Filament\Resources\Establecimientos\EstablecimientoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEstablecimientos extends ManageRecords
{
    protected static string $resource = EstablecimientoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nueva sucursal'),
        ];
    }
}
