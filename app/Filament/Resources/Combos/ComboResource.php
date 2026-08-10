<?php

namespace App\Filament\Resources\Combos;

use App\Application\Catalog\SyncComboOptions;
use App\Enums\DisponibilidadProducto;
use App\Filament\Resources\Combos\Pages\ManageCombos;
use App\Models\Combo;
use App\Models\Producto;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
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

class ComboResource extends Resource
{
    protected static ?string $model = Combo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $navigationLabel = 'Combos';

    protected static string|UnitEnum|null $navigationGroup = 'CatÃ¡logo';

    protected static ?string $modelLabel = 'combo';

    protected static ?string $pluralModelLabel = 'combos';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('gestionar_combos') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos del combo')->schema([
                Grid::make(2)->schema([
                    TextInput::make('nombre')->label('Nombre')->required()->maxLength(100),
                    TextInput::make('precio_fijo')->label('Precio fijo')->numeric()->minValue(0)->prefix('$')->required(),
                    Select::make('disponibilidad')
                        ->label('Disponibilidad')
                        ->options(collect(DisponibilidadProducto::cases())->mapWithKeys(fn (DisponibilidadProducto $state): array => [$state->value => $state->label()])->all())
                        ->default(DisponibilidadProducto::DISPONIBLE->value)
                        ->required()
                        ->native(false),
                    FileUpload::make('imagen_url')
                        ->label('Imagen del combo')
                        ->image()
                        ->disk('public')
                        ->directory('combos')
                        ->visibility('public')
                        ->maxSize(4096),
                ]),
            ]),
            Section::make('Grupos de selecciÃ³n')
                ->description('Define quÃ© puede escoger el cliente y cuÃ¡ntas unidades debe completar.')
                ->schema([
                    Repeater::make('opciones')
                        ->label('Opciones del combo')
                        ->defaultItems(1)
                        ->minItems(1)
                        ->required()
                        ->addActionLabel('Agregar grupo')
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('nombre')->label('Nombre del grupo')->placeholder('Pupusas')->required()->maxLength(50),
                                TextInput::make('cantidad_requerida')->label('Cantidad requerida')->numeric()->minValue(1)->required(),
                                Toggle::make('es_obligatorio')->label('Grupo obligatorio')->default(true),
                                Select::make('producto_ids')
                                    ->label('Productos permitidos')
                                    ->options(fn (): array => Producto::query()->with('categoria')->orderBy('nombre')->get()->mapWithKeys(fn (Producto $product): array => [$product->getKey() => $product->nombre . ' · ' . ($product->categoria?->nombre ?? 'Sin categoría')])->all())
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->minItems(1)
                                    ->native(false)
                                    ->columnSpanFull(),
                            ]),
                        ])
                        ->itemLabel(fn (array $state): ?string => filled($state['nombre'] ?? null) ? (string) $state['nombre'] : 'Nuevo grupo'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('imagen_url')->label('Imagen')->disk('public')->circular(),
                TextColumn::make('nombre')->label('Combo')->searchable()->sortable(),
                TextColumn::make('precio_fijo')->label('Precio')->money('USD')->sortable(),
                TextColumn::make('opciones_combo_count')->label('Grupos')->counts('opcionesCombo'),
                TextColumn::make('disponibilidad')->label('Disponibilidad')->badge()->formatStateUsing(fn (?DisponibilidadProducto $state): string => $state?->label() ?? 'Sin estado')->color(fn (?DisponibilidadProducto $state): string => $state === DisponibilidadProducto::DISPONIBLE ? 'success' : 'gray'),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Editar')
                    ->mutateRecordDataUsing(function (array $data, Combo $record): array {
                        return [
                            ...$data,
                            'opciones' => app(SyncComboOptions::class)->formState($record),
                        ];
                    })
                    ->after(function (EditAction $action): void {
                        $data = $action->getData();
                        app(SyncComboOptions::class)->handle($action->getRecord(), $data['opciones'] ?? []);
                    }),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->before(function (DeleteAction $action): void {
                        $combo = $action->getRecord();

                        if ($combo instanceof Combo && $combo->detallesPedido()->exists()) {
                            Notification::make()
                                ->title('No se puede eliminar este combo')
                                ->body('Tiene historial de ventas. Márcalo como no disponible.')
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
            'index' => ManageCombos::route('/'),
        ];
    }
}
