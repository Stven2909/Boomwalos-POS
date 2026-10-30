<?php

namespace App\Filament\Resources\Mesas;

use App\Contracts\EstablishmentContextInterface;
use App\Enums\EstadoMesa;
use App\Filament\Resources\Mesas\Pages\ManageMesas;
use App\Models\Mesa;
use App\Models\UbicacionMesa;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use UnitEnum;

class MesaResource extends Resource
{
    protected static ?string $model = Mesa::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static ?string $navigationLabel = 'Mesas';

    protected static string|UnitEnum|null $navigationGroup = 'Operación';

    protected static ?string $modelLabel = 'mesa';

    protected static ?string $pluralModelLabel = 'mesas';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('gestionar_mesas') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        $estId = app(EstablishmentContextInterface::class)->idOrNull();

        $ubicaciones = $estId
            ? UbicacionMesa::where('establecimiento_id', $estId)
                ->where('activa', true)
                ->orderBy('orden')
                ->pluck('nombre', 'nombre')
                ->all()
            : [];

        return $schema
            ->components([
                Section::make('Configuración de mesa')
                    ->description('El estado libre u ocupada lo controla el flujo de pedidos.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('numero')
                                    ->label('Número de mesa')
                                    ->required()
                                    ->maxLength(10)
                                    ->unique(
                                        table: 'mesas',
                                        column: 'numero',
                                        modifyRuleUsing: function ($rule) use ($estId) {
                                            return $rule->where('establecimiento_id', $estId)
                                                        ->whereNull('deleted_at');
                                        },
                                        ignoreRecord: true,
                                    ),
                                Select::make('zona')
                                    ->label('Ubicación')
                                    ->options($ubicaciones)
                                    ->native(false)
                                    ->required(),
                                Toggle::make('activa')
                                    ->label('Mesa activa')
                                    ->helperText('Las mesas inactivas no aparecen en el Punto de Venta.')
                                    ->default(true),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $estId = app(EstablishmentContextInterface::class)->idOrNull();

        return $table
            ->query(Mesa::withTrashed()->where('establecimiento_id', $estId))
            ->columns([
                TextColumn::make('numero')->label('Mesa')->sortable()->searchable(),
                TextColumn::make('zona')->label('Ubicación')->badge(),
                TextColumn::make('estado')
                    ->label('Estado operativo')
                    ->badge()
                    ->color(fn (?EstadoMesa $state): string => $state === EstadoMesa::OCUPADA ? 'primary' : 'success')
                    ->formatStateUsing(fn (?EstadoMesa $state): string => $state?->label() ?? 'Sin estado'),
                IconColumn::make('activa')->label('Activa')->boolean(),
                TextColumn::make('deleted_at')->label('Eliminada')->dateTime('d/m/Y')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('zona')
                    ->label('Ubicación')
                    ->options(
                        $estId
                            ? UbicacionMesa::where('establecimiento_id', $estId)
                                ->orderBy('orden')
                                ->pluck('nombre', 'nombre')
                                ->all()
                            : []
                    ),
                SelectFilter::make('estado')
                    ->options(collect(EstadoMesa::cases())->mapWithKeys(fn (EstadoMesa $state): array => [$state->value => $state->label()])->all()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make()->label('Editar'),
                RestoreAction::make()->label('Restaurar'),
                DeleteAction::make()->label('Eliminar'),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMesas::route('/'),
        ];
    }
}
