<?php

namespace App\Filament\Resources\Productos;

use App\Enums\DisponibilidadProducto;
use App\Filament\Resources\Productos\Pages\ManageProductos;
use App\Models\Producto;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use UnitEnum;

class ProductoResource extends Resource
{
    protected static ?string $model = Producto::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCake;

    protected static ?string $navigationLabel = 'Productos';

    protected static string|UnitEnum|null $navigationGroup = 'CatÃ¡logo';

    protected static ?string $modelLabel = 'producto';

    protected static ?string $pluralModelLabel = 'productos';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('gestionar_productos') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos del producto')->schema([
                Grid::make(2)->schema([
                    TextInput::make('nombre')->label('Nombre')->required()->maxLength(100),
                    Select::make('categoria_id')->label('CategorÃ­a')->relationship('categoria', 'nombre')->searchable()->preload()->required()->native(false),
                    TextInput::make('precio')->label('Precio')->numeric()->minValue(0)->prefix('$')->required(),
                    Select::make('disponibilidad')
                        ->label('Disponibilidad')
                        ->options(collect(DisponibilidadProducto::cases())->mapWithKeys(fn (DisponibilidadProducto $state): array => [$state->value => $state->label()])->all())
                        ->default(DisponibilidadProducto::DISPONIBLE->value)
                        ->required()
                        ->native(false),
                    FileUpload::make('imagen_url')
                        ->label('Imagen')
                        ->image()
                        ->disk('public')
                        ->directory('productos')
                        ->visibility('public')
                        ->maxSize(4096)
                        ->helperText('JPG, PNG o WebP. MÃ¡ximo 4 MB.')
                        ->columnSpanFull(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('imagen_url')->label('Imagen')->disk('public')->circular(),
                TextColumn::make('nombre')->label('Producto')->searchable()->sortable(),
                TextColumn::make('categoria.nombre')->label('CategorÃ­a')->sortable(),
                TextColumn::make('precio')->label('Precio')->money('USD')->sortable(),
                TextColumn::make('disponibilidad')->label('Disponibilidad')->badge()->formatStateUsing(fn (?DisponibilidadProducto $state): string => $state?->label() ?? 'Sin estado')->color(fn (?DisponibilidadProducto $state): string => $state === DisponibilidadProducto::DISPONIBLE ? 'success' : 'gray'),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make()->label('Editar'),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->before(function (DeleteAction $action): void {
                        $producto = $action->getRecord();

                        if ($producto instanceof Producto && ($producto->detallesPedido()->exists() || $producto->opcionesComboProductos()->exists())) {
                            Notification::make()
                                ->title('No se puede eliminar este producto')
                                ->body('Tiene historial de ventas o pertenece a un combo. Márcalo como no disponible.')
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
            'index' => ManageProductos::route('/'),
        ];
    }
}
