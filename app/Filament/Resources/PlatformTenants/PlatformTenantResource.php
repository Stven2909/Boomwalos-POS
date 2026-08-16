<?php

namespace App\Filament\Resources\PlatformTenants;

use App\Filament\Resources\PlatformTenants\Pages\ManagePlatformTenants;
use App\Models\Platform\PlatformTenant;
use App\Models\Platform\PlatformUser;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PlatformTenantResource extends Resource
{
    protected static ?string $model = PlatformTenant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $navigationLabel = 'Empresas';

    protected static string|UnitEnum|null $navigationGroup = 'Plataforma';

    protected static ?string $modelLabel = 'empresa';

    protected static ?string $pluralModelLabel = 'empresas';

    public static function canAccess(): bool
    {
        return Filament::auth()->user() instanceof PlatformUser;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Registro de empresa')
                ->description('Esta información pertenece al plano central de la plataforma; los datos fiscales siguen en cada sucursal.')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('display_name')
                            ->label('Nombre comercial')
                            ->required()
                            ->maxLength(150),
                        TextInput::make('slug')
                            ->label('Slug / subdominio')
                            ->required()
                            ->maxLength(80)
                            ->unique(ignoreRecord: true)
                            ->alphaDash(),
                        Select::make('status')
                            ->label('Estado')
                            ->options([
                                'active' => 'Activa',
                                'suspended' => 'Suspendida',
                                'inactive' => 'Inactiva',
                            ])
                            ->required()
                            ->default('active')
                            ->native(false),
                        Select::make('plan_code')
                            ->label('Plan')
                            ->options([
                                'basic' => 'Básico',
                                'smart_qr' => 'Smart QR',
                                'pro' => 'Pro DTE',
                                'enterprise' => 'Cadena',
                            ])
                            ->required()
                            ->default('basic')
                            ->native(false),
                    ]),
            ]),
            Section::make('Marca de la empresa')->schema([
                Grid::make(2)->schema([
                    TextInput::make('logo_path')->label('Ruta del logo')->maxLength(255),
                    TextInput::make('favicon_path')->label('Ruta del favicon')->maxLength(255),
                    ColorPicker::make('primary_color')->label('Color principal'),
                    ColorPicker::make('secondary_color')->label('Color secundario'),
                    TextInput::make('ticket_header')->label('Encabezado del ticket')->maxLength(150),
                    TextInput::make('contact_phone')->label('Teléfono de contacto')->maxLength(40),
                    TextInput::make('contact_email')->label('Correo de contacto')->email()->maxLength(150),
                    Textarea::make('ticket_footer')->label('Pie del ticket')->rows(3)->maxLength(1000),
                ]),
            ]),
            Section::make('Base de datos operativa')
                ->description('En modo database cada empresa debe tener una base independiente. La contraseña se cifra antes de guardarse.')
                ->relationship('connection')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('driver')
                            ->label('Motor')
                            ->options([
                                'mysql' => 'MySQL',
                                'mariadb' => 'MariaDB',
                                'pgsql' => 'PostgreSQL',
                                'sqlsrv' => 'SQL Server',
                            ])
                            ->default('mysql')
                            ->required()
                            ->native(false),
                        TextInput::make('host')->label('Host')->required(fn (): bool => config('tenancy.mode') === 'database')->maxLength(255),
                        TextInput::make('port')->label('Puerto')->numeric()->default(3306),
                        TextInput::make('database')->label('Base de datos')->required(fn (): bool => config('tenancy.mode') === 'database')->maxLength(255),
                        TextInput::make('username')->label('Usuario')->maxLength(255),
                        TextInput::make('password')
                            ->label('Contraseña')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state)),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')->label('Empresa')->searchable()->sortable(),
                TextColumn::make('slug')->label('Slug')->searchable(),
                TextColumn::make('plan_code')->label('Plan')->badge(),
                TextColumn::make('status')->label('Estado')->badge(),
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
            'index' => ManagePlatformTenants::route('/'),
        ];
    }
}
