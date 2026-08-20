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

    protected static ?string $slug = 'configuracion-fiscals';

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
                                Select::make('ambiente')
                                    ->label('Ambiente de facturación')
                                    ->options([
                                        '00' => 'Pruebas / Homologación (00)',
                                        '01' => 'Producción (01) - Oficial MH',
                                    ])
                                    ->default('00')
                                    ->helperText('En Producción (01), los comprobantes emitidos tienen validez legal ante el Ministerio de Hacienda.')
                                    ->required(),
                                TextInput::make('razon_social')
                                    ->label('Razón social / Nombre comercial')
                                    ->maxLength(200),
                                TextInput::make('nit')
                                    ->label('NIT')
                                    ->placeholder('0614-010190-101-1')
                                    ->maxLength(30),
                                TextInput::make('nrc')
                                    ->label('NRC')
                                    ->placeholder('123456-7')
                                    ->maxLength(30),
                                TextInput::make('giro')
                                    ->label('Giro comercial')
                                    ->placeholder('Venta de comidas y bebidas')
                                    ->maxLength(250),
                                TextInput::make('codigo_establecimiento')
                                    ->label('Código Establecimiento MH')
                                    ->default('0001')
                                    ->maxLength(10),
                                TextInput::make('codigo_punto_venta')
                                    ->label('Código Punto de Venta MH')
                                    ->default('001')
                                    ->maxLength(10),
                                TextInput::make('cliente_key')
                                    ->label('Clave del cliente (Client-ID)')
                                    ->required()
                                    ->maxLength(100),
                                TextInput::make('cliente_secret')
                                    ->label('Secreto del cliente (Client Secret)')
                                    ->password()
                                    ->revealable()
                                    ->helperText('Se guarda cifrado con AES-256. En edición, déjalo vacío para conservarlo.')
                                    ->required(fn (string $operation): bool => $operation === 'create')
                                    ->dehydrated(fn (?string $state): bool => filled($state)),
                                TextInput::make('intentos_maximos')
                                    ->label('Intentos máximos de envío')
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
                TextColumn::make('ambiente')
                    ->label('Ambiente')
                    ->badge()
                    ->color(fn (?string $state): string => $state === '01' ? 'primary' : 'gray')
                    ->formatStateUsing(fn (?string $state): string => $state === '01' ? 'Producción (01)' : 'Pruebas (00)')
                    ->alignCenter(),
                IconColumn::make('fiscal_habilitada')->label('Habilitada')->boolean()->alignCenter(),
                TextColumn::make('razon_social')->label('Razón Social')->searchable(),
                TextColumn::make('cliente_key')->label('Client ID')->searchable(),
                TextColumn::make('intentos_maximos')->label('Intentos')->alignCenter(),
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
