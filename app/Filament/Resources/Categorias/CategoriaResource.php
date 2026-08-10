<?php

namespace App\Filament\Resources\Categorias;

use App\Filament\Resources\Categorias\Pages\ManageCategorias;
use App\Models\Categoria;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
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
use UnitEnum;

class CategoriaResource extends Resource
{
    protected static ?string $model = Categoria::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Categorías';

    protected static string|UnitEnum|null $navigationGroup = 'Catálogo';

    protected static ?string $modelLabel = 'categoría';

    protected static ?string $pluralModelLabel = 'categorías';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('gestionar_productos') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos de categoría')->schema([
                Grid::make(2)->schema([
                    TextInput::make('nombre')->label('Nombre')->required()->maxLength(50)->unique(ignoreRecord: true),
                    Toggle::make('activa')->label('Categoría activa')->default(true),
                    Textarea::make('descripcion')->label('Descripción')->rows(3)->maxLength(500)->columnSpanFull(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('productos_count')->label('Productos')->counts('productos'),
                IconColumn::make('activa')->label('Activa')->boolean(),
            ])
            ->recordActions([
                EditAction::make()->label('Editar'),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->before(function (DeleteAction $action): void {
                        $categoria = $action->getRecord();

                        if ($categoria instanceof Categoria && $categoria->productos()->exists()) {
                            Notification::make()
                                ->title('No se puede eliminar esta categoría')
                                ->body('Tiene productos asociados. Desactívala para conservar el catálogo.')
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
            'index' => ManageCategorias::route('/'),
        ];
    }
}
