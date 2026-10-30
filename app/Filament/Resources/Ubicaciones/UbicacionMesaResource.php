<?php

namespace App\Filament\Resources\Ubicaciones;

use App\Contracts\EstablishmentContextInterface;
use App\Filament\Resources\Ubicaciones\Pages\ManageUbicaciones;
use App\Models\Mesa;
use App\Models\UbicacionMesa;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class UbicacionMesaResource extends Resource
{
    protected static ?string $model = UbicacionMesa::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?string $navigationLabel = 'Ubicaciones';

    protected static string|UnitEnum|null $navigationGroup = 'Operación';

    protected static ?string $modelLabel = 'ubicación';

    protected static ?string $pluralModelLabel = 'ubicaciones';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('gestionar_mesas') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos de la ubicación')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('nombre')
                                    ->label('Nombre')
                                    ->required()
                                    ->maxLength(60)
                                    ->unique(
                                        table: 'ubicaciones_mesa',
                                        column: 'nombre',
                                        modifyRuleUsing: function ($rule) {
                                            $estId = app(EstablishmentContextInterface::class)->id();
                                            return $rule->where('establecimiento_id', $estId);
                                        },
                                        ignoreRecord: true,
                                    ),
                                TextInput::make('orden')
                                    ->label('Orden')
                                    ->numeric()
                                    ->required()
                                    ->default(0)
                                    ->minValue(0),
                                Toggle::make('activa')
                                    ->label('Activa')
                                    ->helperText('Las ubicaciones inactivas no aparecen en el POS.')
                                    ->default(true),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(
                UbicacionMesa::query()
                    ->where('establecimiento_id', app(EstablishmentContextInterface::class)->id())
                    ->orderBy('orden')
            )
            ->defaultSort('orden')
            ->reorderable('orden')
            ->columns([
                TextColumn::make('orden')->label('#')->sortable(),
                TextColumn::make('nombre')->label('Nombre')->sortable()->searchable(),
                TextColumn::make('mesas_count')
                    ->label('Mesas activas')
                    ->counts('mesas')
                    ->badge(),
                IconColumn::make('activa')->label('Activa')->boolean(),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Editar')
                    ->after(function (UbicacionMesa $record, array $data) use (&$table): void {
                        // Si cambió el nombre, actualizar mesas.zona en cascada dentro de una transacción.
                        $oldNombre = $record->getOriginal('nombre');
                        if (isset($data['nombre']) && $data['nombre'] !== $oldNombre) {
                            DB::transaction(function () use ($record, $oldNombre, $data): void {
                                Mesa::withTrashed()
                                    ->where('establecimiento_id', $record->establecimiento_id)
                                    ->where('zona', $oldNombre)
                                    ->update(['zona' => $data['nombre']]);
                            });
                        }
                    }),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->before(function (DeleteAction $action, UbicacionMesa $record): void {
                        $tieneActivas = Mesa::query()
                            ->where('establecimiento_id', $record->establecimiento_id)
                            ->where('zona', $record->nombre)
                            ->exists();

                        if ($tieneActivas) {
                            Notification::make()
                                ->title('No se puede eliminar')
                                ->body('La ubicación tiene mesas activas. Elimínalas o muévelas antes de borrar la ubicación.')
                                ->danger()
                                ->send();
                            $action->halt();
                        }
                    }),
            ])
            ->toolbarActions([
                CreateAction::make()
                    ->label('Nueva ubicación')
                    ->before(function (): void {
                        app(EstablishmentContextInterface::class)->idOrNull()
                            ?? throw ValidationException::withMessages([
                                'establecimiento' => 'Selecciona la sucursal en la que deseas trabajar.',
                            ]);
                    })
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['establecimiento_id'] = app(EstablishmentContextInterface::class)->id();
                        return $data;
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUbicaciones::route('/'),
        ];
    }
}
