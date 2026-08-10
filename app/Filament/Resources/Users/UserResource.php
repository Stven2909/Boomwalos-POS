<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Usuarios';

    protected static string|UnitEnum|null $navigationGroup = 'Administración';

    protected static ?string $modelLabel = 'usuario';

    protected static ?string $pluralModelLabel = 'usuarios';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos del usuario')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('nombre')
                                    ->label('Nombre completo')
                                    ->required()
                                    ->maxLength(100),
                                Select::make('rol')
                                    ->label('Rol')
                                    ->options([
                                        'cajero' => 'Cajero',
                                        'administrador' => 'Administrador',
                                    ])
                                    ->default('cajero')
                                    ->required()
                                    ->native(false),
                                TextInput::make('usuario')
                                    ->label('Código de usuario')
                                    ->helperText('Para cajeros utiliza entre 2 y 6 dígitos, por ejemplo 01.')
                                    ->autocomplete('off')
                                    ->required()
                                    ->maxLength(50)
                                    ->unique(ignoreRecord: true)
                                    ->rules(fn (Get $get): array => $get('rol') === 'cajero'
                                        ? ['regex:/^\d{2,6}$/']
                                        : []),
                                TextInput::make('email')
                                    ->label('Correo electrónico')
                                    ->autocomplete('off')
                                    ->email()
                                    ->maxLength(100)
                                    ->unique(ignoreRecord: true)
                                    ->required(fn (Get $get): bool => $get('rol') === 'administrador'),
                                TextInput::make('password')
                                    ->label('PIN / contraseña')
                                    ->autocomplete('new-password')
                                    ->password()
                                    ->revealable()
                                    ->helperText('Cajeros: PIN de 4 dígitos. En edición déjalo vacío para conservarlo.')
                                    ->required(fn (string $operation): bool => $operation === 'create')
                                    ->dehydrated(fn (?string $state): bool => filled($state))
                                    ->rules(fn (Get $get): array => $get('rol') === 'cajero'
                                        ? ['digits:4']
                                        : []),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('usuario')
                    ->label('Código / usuario')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Correo')
                    ->placeholder('Sin correo'),
                TextColumn::make('rol')
                    ->label('Rol')
                    ->state(fn (User $record): string => $record->getRoleNames()->first() ?? 'Sin rol')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'administrador' ? 'primary' : 'gray'),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make()
                    ->label('Editar')
                    ->mutateRecordDataUsing(fn (array $data, User $record): array => [
                        ...$data,
                        'rol' => $record->getRoleNames()->first() ?? 'cajero',
                    ])
                    ->after(function (EditAction $action): void {
                        $record = $action->getRecord();
                        $role = $action->getData()['rol'] ?? null;

                        if ($record instanceof User && is_string($role)) {
                            $record->syncRoles($role);
                        }
                    }),
                DeleteAction::make()->label('Eliminar'),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
