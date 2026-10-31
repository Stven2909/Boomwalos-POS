<?php

namespace App\Filament\Resources\Mesas\Pages;

use App\Enums\EstadoMesa;
use App\Filament\Resources\Mesas\MesaResource;
use App\Contracts\EstablishmentContextInterface;
use App\Models\Mesa;
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
                })
                ->using(function (array $data): Mesa {
                    // Si el número pertenece a una mesa eliminada, se restaura
                    // en lugar de fallar por el índice único de la base.
                    $eliminada = Mesa::withTrashed()
                        ->where('establecimiento_id', $data['establecimiento_id'])
                        ->where('numero', $data['numero'])
                        ->whereNotNull('deleted_at')
                        ->first();

                    if ($eliminada) {
                        $eliminada->restore();
                        $eliminada->update([
                            'zona' => $data['zona'],
                            'activa' => $data['activa'] ?? true,
                            'estado' => EstadoMesa::LIBRE,
                        ]);

                        return $eliminada;
                    }

                    return Mesa::create($data);
                }),
        ];
    }
}
