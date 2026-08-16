<?php

namespace App\Filament\Resources\Establecimientos;

use App\Filament\Resources\Establecimientos\Pages\ManageEstablecimientos;
use App\Models\Establecimiento;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class EstablecimientoResource extends Resource
{
    protected static ?string $model = Establecimiento::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $navigationLabel = 'Sucursales';

    protected static string|UnitEnum|null $navigationGroup = 'Administración';

    protected static ?string $modelLabel = 'sucursal';

    protected static ?string $pluralModelLabel = 'sucursales';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('gestionar_establecimientos') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identidad de la sucursal')
                ->description('La sucursal pertenece a la empresa actual y puede tener su propia operación y configuración fiscal.')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('nombre')
                            ->label('Nombre de la sucursal')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('direccion')
                            ->label('Dirección')
                            ->required()
                            ->maxLength(500),
                        TextInput::make('codigo_establecimiento')
                            ->label('Código de establecimiento')
                            ->maxLength(10),
                        TextInput::make('codigo_punto_venta')
                            ->label('Código de punto de venta')
                            ->maxLength(10),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')->label('Sucursal')->searchable()->sortable(),
                TextColumn::make('direccion')->label('Dirección')->searchable(),
                TextColumn::make('codigo_establecimiento')->label('Código establecimiento')->placeholder('Sin configurar'),
                TextColumn::make('usuarios_count')->counts('usuarios')->label('Personal asignado')->alignCenter(),
            ])
            ->recordActions([
                EditAction::make()->label('Editar'),
                DeleteAction::make()->label('Eliminar'),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEstablecimientos::route('/'),
        ];
    }
}
