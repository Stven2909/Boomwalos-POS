<?php

namespace App\Filament\Resources\Impresoras;

use App\Enums\TipoConexionImpresora;
use App\Enums\TipoImpresora;
use App\Filament\Resources\Impresoras\Pages\CreateImpresora;
use App\Filament\Resources\Impresoras\Pages\EditImpresora;
use App\Filament\Resources\Impresoras\Pages\ListImpresoras;
use App\Models\Impresora;
use App\Services\Printing\PrinterTestService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ImpresoraResource extends Resource
{
    protected static ?string $model = Impresora::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPrinter;

    protected static string|UnitEnum|null $navigationGroup = 'Ajustes';

    protected static ?int $navigationSort = 25;

    protected static ?string $modelLabel = 'Impresora';

    protected static ?string $pluralModelLabel = 'Impresoras';

    protected static ?string $slug = 'impresoras';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('ver_impresoras');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Datos básicos')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('nombre')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(50),
                        Select::make('tipo')
                            ->label('Tipo')
                            ->options(TipoImpresora::class)
                            ->required(),
                    ]),
                    Select::make('establecimiento_id')
                        ->label('Sucursal')
                        ->relationship('establecimiento', 'nombre')
                        ->placeholder('Global (todas las sucursales)')
                        ->nullable(),
                ]),

            Section::make('Conexión')
                ->schema([
                    Select::make('conexion')
                        ->label('Tipo de conexión')
                        ->options(TipoConexionImpresora::class)
                        ->default(TipoConexionImpresora::PDF)
                        ->required()
                        ->reactive(),
                    TextInput::make('ip')
                        ->label('Dirección IP')
                        ->placeholder('192.168.1.100')
                        ->visible(fn ($get) => ($get('conexion') instanceof TipoConexionImpresora ? $get('conexion')->value : $get('conexion')) === TipoConexionImpresora::RED->value),
                    TextInput::make('puerto')
                        ->label('Puerto')
                        ->default(9100)
                        ->numeric()
                        ->visible(fn ($get) => ($get('conexion') instanceof TipoConexionImpresora ? $get('conexion')->value : $get('conexion')) === TipoConexionImpresora::RED->value),
                    TextInput::make('dispositivo_usb')
                        ->label('Dispositivo USB')
                        ->placeholder('/dev/usb/lp0 o nombre de impresora')
                        ->visible(fn ($get) => ($get('conexion') instanceof TipoConexionImpresora ? $get('conexion')->value : $get('conexion')) === TipoConexionImpresora::USB->value),
                    Toggle::make('activa')
                        ->label('Activa')
                        ->default(true),
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
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (TipoImpresora $state): string => $state === TipoImpresora::COMANDA ? 'warning' : 'info'),
                TextColumn::make('conexion')
                    ->label('Conexión')
                    ->badge()
                    ->color(fn (TipoConexionImpresora $state): string => match ($state) {
                        TipoConexionImpresora::RED => 'success',
                        TipoConexionImpresora::USB => 'gray',
                        TipoConexionImpresora::PDF => 'primary',
                    }),
                TextColumn::make('direccion')
                    ->label('Dirección')
                    ->getStateUsing(fn ($record): string => $record->direccionConexion()),
                TextColumn::make('establecimiento.nombre')
                    ->label('Sucursal')
                    ->default('Global'),
                IconColumn::make('activa')
                    ->label('Activa')
                    ->boolean(),
                TextColumn::make('ultima_conexion_exitosa_at')
                    ->label('Última prueba')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Nunca'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Action::make('probar')
                    ->label('Probar')
                    ->icon('heroicon-o-signal')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Probar impresora')
                    ->modalDescription(fn ($record) => $record->conexion === TipoConexionImpresora::PDF
                        ? 'Se generará y abrirá un ticket de prueba en PDF para verificar el formato.'
                        : 'Se enviará un ticket de prueba físico a esta impresora.')
                    ->action(function ($record, $livewire): void {
                        try {
                            $pdfUrl = app(PrinterTestService::class)->probar($record);
                            if ($pdfUrl) {
                                Notification::make()
                                    ->title('Prueba de PDF Generada')
                                    ->body('El documento PDF fue generado correctamente.')
                                    ->success()
                                    ->actions([
                                        \Filament\Notifications\Actions\Action::make('abrir_pdf')
                                            ->label('Abrir PDF')
                                            ->url($pdfUrl, shouldOpenInNewTab: true)
                                            ->button(),
                                    ])
                                    ->send();

                                $livewire->js("window.open('{$pdfUrl}', '_blank')");
                            } else {
                                Notification::make()
                                    ->title('Prueba exitosa')
                                    ->body("Conexión física verificada: {$record->direccionConexion()}")
                                    ->success()
                                    ->send();
                            }
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Error de conexión')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImpresoras::route('/'),
            'create' => CreateImpresora::route('/create'),
            'edit' => EditImpresora::route('/{record}/edit'),
        ];
    }
}
