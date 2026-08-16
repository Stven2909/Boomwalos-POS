<?php

namespace App\Filament\Resources\PlatformTenants\Pages;

use App\Filament\Resources\PlatformTenants\PlatformTenantResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePlatformTenants extends ManageRecords
{
    protected static string $resource = PlatformTenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nueva empresa'),
        ];
    }
}
