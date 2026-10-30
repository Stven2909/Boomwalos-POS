<?php

namespace App\Filament\Resources\Mesas\Pages;

use App\Enums\EstadoMesa;
use App\Filament\Resources\Mesas\MesaResource;
use App\Contracts\EstablishmentContextInterface;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Validation\ValidationException;

class ManageMesas extends ManageRecords
{
    protected static string $resource = MesaResource::class;

    protected function requireEstablishmentContext(): void
    {
        $establecimientoId = app(EstablishmentContextInterface::class)->idOrNull();

        if ($establecimientoId === null) {
            throw ValidationException::withMessages([
                'establecimiento' => 'Selecciona la sucursal en la que deseas trabajar.',
            ]);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make('create')
                ->label('Nueva mesa')
                ->before(function (): void {
                    $this->requireEstablishmentContext();
                })
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
