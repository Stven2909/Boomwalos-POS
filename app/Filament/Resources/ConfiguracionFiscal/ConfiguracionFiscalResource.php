<?php

namespace App\Filament\Resources\ConfiguracionFiscal;

use App\Filament\Resources\ConfiguracionFiscal\Pages\ManageConfiguracionFiscal;
use App\Models\ConfiguracionFiscal;
use App\Models\Establecimiento;
use BackedEnum;
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
use Filament\Tables\Table;
use UnitEnum;

class ConfiguracionFiscalResource extends Resource
{
    protected static ?string $model = ConfiguracionFiscal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $navigationLabel = 'Configuración fiscal';

    protected static string|UnitEnum|null $navigationGroup = 'Fiscal';

    protected static ?string $modelLabel = 'configuración fiscal';

    protected static ?string $pluralModelLabel = 'Configuración fiscal';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('gestionar_configuracion_fiscal') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Conexión con la API fiscal')
                    ->description('Habilita el envío de ventas a la API fiscal para este establecimiento.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('establecimiento_id')
                                    ->label('Establecimiento')
                                    ->options(fn (): array => Establecimiento::query()->orderBy('nombre')->pluck('nombre', 'id')->all())
                                    ->searchable()
                                    ->native(false)
                                    ->required()
                                    ->unique(ignoreRecord: true),
                                Toggle::make('fiscal_habilitada')
                                    ->label('Fiscal habilitada')
                                    ->helperText('Al activarlo, cada cobro generará una venta fiscal en la cola de envío.')
                                    ->default(false),
                                TextInput::make('razon_social')
                                    ->label('Razón social')
                                    ->maxLength(200),
                                TextInput::make('nit')
                                    ->label('NIT')
                                    ->maxLength(30),
                                TextInput::make('nrc')
                                    ->label('NRC')
                                    ->maxLength(30),
                                TextInput::make('cliente_key')
                                    ->label('Clave del cliente')
                                    ->required()
                                    ->maxLength(100),
                                TextInput::make('cliente_secret')
                                    ->label('Secreto del cliente')
                                    ->password()
                                    ->revealable()
                                    ->helperText('Se guarda cifrado. En edición, déjalo vacío para conservarlo.')
                                    ->required(fn (string $operation): bool => $operation === 'create')
                                    ->dehydrated(fn (?string $state): bool => filled($state)),
                                TextInput::make('intentos_maximos')
                                    ->label('Intentos máximos')
                                    ->numeric()
                                    ->required()
                                    ->default(3)
                                    ->minValue(1)
                                    ->maxValue(10),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('establecimiento.nombre')->label('Establecimiento')->searchable()->sortable(),
                IconColumn::make('fiscal_habilitada')->label('Habilitada')->boolean(),
                TextColumn::make('cliente_key')->label('Clave del cliente')->searchable(),
                TextColumn::make('intentos_maximos')->label('Intentos máximos')->alignCenter(),
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
            'index' => ManageConfiguracionFiscal::route('/'),
        ];
    }
}
