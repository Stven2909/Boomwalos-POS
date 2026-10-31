<?php

namespace App\Filament\Resources\Ubicaciones;

use App\Contracts\EstablishmentContextInterface;
use App\Enums\FlujoUbicacionMesa;
use App\Filament\Resources\Ubicaciones\Pages\ManageUbicaciones;
use App\Models\Mesa;
use App\Models\UbicacionMesa;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
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
        $estId = app(EstablishmentContextInterface::class)->idOrNull();

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
                                        modifyRuleUsing: function ($rule) use ($estId) {
                                            return $estId === null
                                                ? $rule->where('establecimiento_id', 0)
                                                : $rule->where('establecimiento_id', $estId);
                                        },
                                        ignoreRecord: true,
                                    ),
                                Select::make('flujo')
                                    ->label('Tipo de ubicación')
                                    ->options(collect(FlujoUbicacionMesa::cases())->mapWithKeys(
                                        fn (FlujoUbicacionMesa $flujo): array => [$flujo->value => $flujo->label()],
                                    )->all())
                                    ->default(FlujoUbicacionMesa::MESAS->value)
                                    ->required()
                                    ->native(false)
                                    ->helperText('Mesas: plano de mesas. Para llevar: tablero de pedidos para llevar, sin mesas.'),
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
        $estId = app(EstablishmentContextInterface::class)->idOrNull();

        $query = UbicacionMesa::query()->orderBy('orden');

        if ($estId === null) {
            $query->whereRaw('1 = 0');
        } else {
            $query->where('establecimiento_id', $estId);
        }

        return $table
            ->query($query)
            ->defaultSort('orden')
            ->reorderable('orden')
            ->columns([
                TextColumn::make('orden')->label('#')->sortable(),
                TextColumn::make('nombre')->label('Nombre')->sortable()->searchable(),
                TextColumn::make('flujo')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?FlujoUbicacionMesa $state): string => $state?->label() ?? 'Mesas'),
                TextColumn::make('mesas_count')
                    ->label('Mesas')
                    ->state(fn (UbicacionMesa $record): int => $record->mesas()->count())
                    ->badge(),
                IconColumn::make('activa')->label('Activa')->boolean(),
            ])
            ->recordActions([
                EditAction::make()->label('Editar'),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->before(function (DeleteAction $action, UbicacionMesa $record): void {
                        $tieneMesas = Mesa::query()
                            ->where('establecimiento_id', $record->establecimiento_id)
                            ->where('zona', $record->nombre)
                            ->exists();

                        if ($tieneMesas) {
                            Notification::make()
                                ->title('No se puede eliminar')
                                ->body('La ubicación tiene mesas asignadas (activas o inactivas). Elimínalas o muévelas antes de borrar la ubicación.')
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
                        $data['flujo'] ??= FlujoUbicacionMesa::MESAS->value;

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
