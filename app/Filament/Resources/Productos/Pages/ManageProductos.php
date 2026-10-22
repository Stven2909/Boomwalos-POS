<?php

namespace App\Filament\Resources\Productos\Pages;

use App\Contracts\EstablishmentContextInterface;
use App\Filament\Pages\EstablishmentSelection;
use App\Filament\Resources\Productos\ProductoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProductos extends ManageRecords
{
    protected static string $resource = ProductoResource::class;

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
        return [CreateAction::make()->label('Nuevo producto')];
    }
}
