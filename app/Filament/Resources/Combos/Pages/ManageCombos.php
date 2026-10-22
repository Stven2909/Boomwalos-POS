<?php

namespace App\Filament\Resources\Combos\Pages;

use App\Contracts\EstablishmentContextInterface;
use App\Filament\Pages\EstablishmentSelection;
use App\Filament\Resources\Combos\ComboResource;
use App\Application\Catalog\SyncComboOptions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCombos extends ManageRecords
{
    protected static string $resource = ComboResource::class;

    public function mount(): void
    {
        $context = app(EstablishmentContextInterface::class);

        if ($context->idOrNull() === null && $context->accessible()->count() > 1) {
            $this->redirect(EstablishmentSelection::getUrl());

            return;
        }

        parent::mount();
    }

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
