<?php

namespace App\Filament\Resources\Mesas\Pages;

use App\Enums\EstadoMesa;
use App\Filament\Resources\Mesas\MesaResource;
use App\Contracts\EstablishmentContextInterface;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageMesas extends ManageRecords
{
    protected static string $resource = MesaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva mesa')
                ->mutateFormDataUsing(function (array $data): array {
                    return [
                        ...$data,
                        'establecimiento_id' => app(EstablishmentContextInterface::class)->id(),
                        'estado' => EstadoMesa::LIBRE,
                    ];
                }),
        ];
    }
}
