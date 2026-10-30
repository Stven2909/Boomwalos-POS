<?php

namespace App\Filament\Resources\Ubicaciones\Pages;

use App\Filament\Resources\Ubicaciones\UbicacionMesaResource;
use Filament\Resources\Pages\ManageRecords;

class ManageUbicaciones extends ManageRecords
{
    protected static string $resource = UbicacionMesaResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
