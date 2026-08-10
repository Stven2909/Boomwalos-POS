<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageUsers extends ManageRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo usuario')
                ->after(function (CreateAction $action): void {
                    $record = $action->getRecord();
                    $role = $action->getData()['rol'] ?? null;

                    if ($record instanceof User && is_string($role)) {
                        $record->syncRoles($role);
                    }
                }),
        ];
    }
}
