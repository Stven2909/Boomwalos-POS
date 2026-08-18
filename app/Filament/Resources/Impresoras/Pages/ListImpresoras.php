<?php

namespace App\Filament\Resources\Impresoras\Pages;

use App\Filament\Resources\Impresoras\ImpresoraResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListImpresoras extends ListRecords
{
    protected static string $resource = ImpresoraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
