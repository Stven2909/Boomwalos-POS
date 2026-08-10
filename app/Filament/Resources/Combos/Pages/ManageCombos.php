<?php

namespace App\Filament\Resources\Combos\Pages;

use App\Filament\Resources\Combos\ComboResource;
use App\Application\Catalog\SyncComboOptions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCombos extends ManageRecords
{
    protected static string $resource = ComboResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo combo')
                ->after(function (CreateAction $action): void {
                    $data = $action->getData();
                    app(SyncComboOptions::class)->handle($action->getRecord(), $data['opciones'] ?? []);
                }),
        ];
    }
}
