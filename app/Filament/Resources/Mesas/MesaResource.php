<?php

namespace App\Filament\Resources\Mesas;

use App\Enums\EstadoMesa;
use App\Enums\ZonaMesa;
use App\Filament\Resources\Mesas\Pages\ManageMesas;
use App\Models\Establecimiento;
use App\Models\Mesa;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
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
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use UnitEnum;

class MesaResource extends Resource
{
    protected static ?string $model = Mesa::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static ?string $navigationLabel = 'Mesas';

    protected static string|UnitEnum|null $navigationGroup = 'OperaciÃ³n';

    protected static ?string $modelLabel = 'mesa';

    protected static ?string $pluralModelLabel = 'mesas';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('gestionar_mesas') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('ConfiguraciÃ³n de mesa')
                    ->description('El estado libre u ocupada lo controla el flujo de pedidos.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('numero')
                                    ->label('NÃºmero de mesa')
                                    ->required()
                                    ->maxLength(10)
                                    ->unique(ignoreRecord: true),
                                Select::make('zona')
                                    ->label('Zona')
                                    ->options(collect(ZonaMesa::cases())->mapWithKeys(fn (ZonaMesa $zone): array => [$zone->value => $zone->label()])->all())
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
        return $table
            ->columns([
                TextColumn::make('numero')->label('Mesa')->sortable()->searchable(),
                TextColumn::make('zona')->label('Zona')->badge()->formatStateUsing(fn (?ZonaMesa $state): string => $state?->label() ?? 'Sin zona'),
                TextColumn::make('estado')->label('Estado operativo')->badge()->color(fn (?EstadoMesa $state): string => $state === EstadoMesa::OCUPADA ? 'primary' : 'success')->formatStateUsing(fn (?EstadoMesa $state): string => $state?->label() ?? 'Sin estado'),
                IconColumn::make('activa')->label('Activa')->boolean(),
            ])
            ->filters([
                SelectFilter::make('zona')
                    ->options(collect(ZonaMesa::cases())->mapWithKeys(fn (ZonaMesa $zone): array => [$zone->value => $zone->label()])->all()),
                SelectFilter::make('estado')
                    ->options(collect(EstadoMesa::cases())->mapWithKeys(fn (EstadoMesa $state): array => [$state->value => $state->label()])->all()),
            ])
            ->recordActions([
                EditAction::make()->label('Editar'),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->before(function (DeleteAction $action): void {
                        $mesa = $action->getRecord();

                        if ($mesa instanceof Mesa && $mesa->pedidos()->exists()) {
                            Notification::make()
                                ->title('No se puede eliminar esta mesa')
                                ->body('Tiene historial de pedidos. Desactívala para conservar la trazabilidad.')
                                ->danger()
                                ->send();
                            $action->halt();
                        }
                    }),
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
