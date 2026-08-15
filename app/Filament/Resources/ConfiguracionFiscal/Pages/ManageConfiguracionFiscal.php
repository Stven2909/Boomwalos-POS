<?php

namespace App\Filament\Resources\ConfiguracionFiscal\Pages;

use App\Filament\Resources\ConfiguracionFiscal\ConfiguracionFiscalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageConfiguracionFiscal extends ManageRecords
{
    protected static string $resource = ConfiguracionFiscalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva configuración'),
        ];
    }
}
