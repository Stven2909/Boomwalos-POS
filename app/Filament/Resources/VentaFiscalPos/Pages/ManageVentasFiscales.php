<?php

namespace App\Filament\Resources\VentaFiscalPos\Pages;

use App\Filament\Resources\VentaFiscalPos\VentaFiscalPosResource;
use Filament\Resources\Pages\ManageRecords;

class ManageVentasFiscales extends ManageRecords
{
    protected static string $resource = VentaFiscalPosResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
