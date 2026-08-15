<?php

namespace App\Filament\Resources\VentaFiscalPos;

use App\Application\Fiscal\FiscalOutboxService;
use App\Enums\EstadoVentaFiscal;
use App\Enums\TipoDocumento;
use App\Filament\Resources\VentaFiscalPos\Pages\ManageVentasFiscales;
use App\Models\VentaFiscalPos;
use App\Services\FiscalDocumentoService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class VentaFiscalPosResource extends Resource
{
    protected static ?string $model = VentaFiscalPos::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Ventas fiscales';

    protected static string|UnitEnum|null $navigationGroup = 'Fiscal';

    protected static ?string $modelLabel = 'venta fiscal';

    protected static ?string $pluralModelLabel = 'ventas fiscales';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('ver_ventas_fiscales') ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('referencia')->label('Referencia')->searchable()->sortable(),
                TextColumn::make('establecimiento.nombre')->label('Establecimiento')->searchable()->sortable(),
                TextColumn::make('estado')->label('Estado')->badge()
                    ->color(fn (?EstadoVentaFiscal $state): string => match ($state) {
                        EstadoVentaFiscal::SINCRONIZADO => 'success',
                        EstadoVentaFiscal::ENVIO_FALLIDO => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (?EstadoVentaFiscal $state): string => $state?->label() ?? 'Sin estado'),
                TextColumn::make('monto_total')->label('Monto')->money('USD')->sortable(),
                TextColumn::make('metodo_pago')->label('Método')->badge(),
                TextColumn::make('fiscal_sale_id')->label('ID fiscal')->placeholder('Sin asignar'),
                TextColumn::make('sincronizado_at')->label('Sincronizado')->dateTime('d/m/Y H:i')->sortable()->placeholder('—'),
                TextColumn::make('created_at')->label('Registrada')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('estado')
                    ->options(collect(EstadoVentaFiscal::cases())
                        ->mapWithKeys(fn (EstadoVentaFiscal $estado): array => [$estado->value => $estado->label()])
                        ->all()),
                SelectFilter::make('establecimiento_id')
                    ->label('Establecimiento')
                    ->relationship('establecimiento', 'nombre'),
            ])
            ->recordActions([
                Action::make('reintentar')
                    ->label('Reintentar envío')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (VentaFiscalPos $record): bool => in_array($record->estado, [EstadoVentaFiscal::NO, EstadoVentaFiscal::ENVIO_FALLIDO], true) && $record->cola !== null)
                    ->authorize(fn (): bool => auth()->user()?->can('reintentar_sincronizacion_fiscal') ?? false)
                    ->action(function (Action $action): void {
                        $venta = $action->getRecord();

                        if (! $venta instanceof VentaFiscalPos || $venta->cola === null) {
                            return;
                        }

                        app(FiscalOutboxService::class)->reintentar($venta->cola);

                        Notification::make()
                            ->title('Envío reencolado')
                            ->body('La venta volverá a intentar sincronizarse.')
                            ->success()
                            ->send();
                    }),
                Action::make('solicitar_documento')
                    ->label('Solicitar documento')
                    ->icon(Heroicon::OutlinedDocumentPlus)
                    ->color('primary')
                    ->visible(fn (VentaFiscalPos $record): bool => $record->estado === EstadoVentaFiscal::SINCRONIZADO)
                    ->authorize(fn (): bool => auth()->user()?->can('solicitar_documento_fiscal') ?? false)
                    ->schema([
                        Select::make('tipo_documento')
                            ->label('Tipo de documento')
                            ->options(collect(TipoDocumento::cases())
                                ->mapWithKeys(fn (TipoDocumento $tipo): array => [$tipo->value => $tipo->label()])
                                ->all())
                            ->native(false)
                            ->required(),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('receptor_nombre')->label('Nombre del receptor')->required()->maxLength(100),
                                TextInput::make('receptor_documento')->label('NIT / DUI')->required()->maxLength(20),
                                Select::make('receptor_tipo_documento')
                                    ->label('Tipo de documento del receptor')
                                    ->options(['NIT' => 'NIT', 'DUI' => 'DUI', 'OTRO' => 'Otro'])
                                    ->native(false)
                                    ->required(),
                            ]),
                    ])
                    ->action(function (Action $action, array $data): void {
                        $venta = $action->getRecord();

                        if (! $venta instanceof VentaFiscalPos) {
                            return;
                        }

                        app(FiscalDocumentoService::class)->solicitar(
                            $venta,
                            TipoDocumento::from($data['tipo_documento']),
                            [
                                'nombre' => $data['receptor_nombre'],
                                'documento' => $data['receptor_documento'],
                                'tipo_documento' => $data['receptor_tipo_documento'],
                            ],
                            auth()->user(),
                        );

                        Notification::make()
                            ->title('Documento solicitado')
                            ->body('La solicitud vence en 48 horas.')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageVentasFiscales::route('/'),
        ];
    }
}
