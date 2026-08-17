<?php

namespace App\Filament\Resources\Categorias;

use App\Filament\Resources\Categorias\Pages\ListCategories;
use App\Models\Categoria;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
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
                    Select::make('parent_id')
                        ->label('Grupo al que pertenece')
                        ->options(fn () => Categoria::query()->groups()->where('activa', true)->pluck('nombre', 'id'))
                        ->nullable()
                        ->default(null)
                        ->placeholder('Ninguno — es un grupo'),
                    Textarea::make('descripcion')->label('Descripción')->rows(2)->maxLength(500)->columnSpanFull(),
                ]),
            ]),
            Section::make('Apariencia en el POS')->schema([
                Grid::make(2)->schema([
                    TextInput::make('icono')
                        ->label('Icono')
                        ->helperText('Emoji (🫓) o ruta de imagen (images/pupusa.svg)')
                        ->maxLength(255)
                        ->placeholder('🥤'),
                    Toggle::make('activa')->label('Visible en el POS')->default(true),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('icono')
                    ->label('')
                    ->html()
                    ->width(50)
                    ->getStateUsing(fn (Categoria $record): string => match ($record->iconoType()) {
                        'image' => '<img src="' . $record->iconoUrl() . '" class="h-6 w-6" alt="">',
                        'emoji' => '<span class="text-xl">' . e($record->icono) . '</span>',
                        default => '<span class="text-gray-300">—</span>',
                    }),
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->html()
                    ->getStateUsing(fn (Categoria $record): string => $record->isGroup()
                        ? '<span class="inline-flex items-center rounded-md bg-purple-100 px-2 py-1 text-xs font-semibold text-purple-800">Grupo</span>'
                        : '<span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700">Categoría</span>'
                    ),
                TextColumn::make('nombre')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('parent.nombre')
                    ->label('Grupo padre')
                    ->getStateUsing(fn (Categoria $record): ?string => $record->parent?->nombre),
                TextColumn::make('children_count')
                    ->label('Subcats')
                    ->counts('children')
                    ->getStateUsing(fn (Categoria $record): int => $record->isGroup() ? $record->children()->count() : 0),
                TextColumn::make('productos_count')
                    ->label('Productos')
                    ->counts('productos')
                    ->sortable(),
                IconColumn::make('activa')->label('Activa')->boolean(),
            ])
            ->defaultSort(fn ($query) => $query->orderBy('parent_id')->orderBy('nombre'))
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

                        if ($categoria instanceof Categoria && $categoria->children()->exists()) {
                            Notification::make()
                                ->title('No se puede eliminar este grupo')
                                ->body('Tiene subcategorías asociadas. Elimínalas primero.')
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
            'index' => ListCategories::route('/'),
        ];
    }
}
