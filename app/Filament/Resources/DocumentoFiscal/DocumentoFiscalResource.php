<?php

namespace App\Filament\Resources\DocumentoFiscal;

use App\Enums\EstadoDocumentoFiscal;
use App\Enums\TipoDocumento;
use App\Filament\Resources\DocumentoFiscal\Pages\ManageDocumentosFiscales;
use App\Models\DocumentoFiscal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class DocumentoFiscalResource extends Resource
{
    protected static ?string $model = DocumentoFiscal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Documentos fiscales';

    protected static string|UnitEnum|null $navigationGroup = 'Fiscal';

    protected static ?string $modelLabel = 'documento fiscal';

    protected static ?string $pluralModelLabel = 'documentos fiscales';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('gestionar_solicitudes_fiscales') ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pedido.numero_seguimiento')->label('Pedido')->searchable()->sortable(),
                TextColumn::make('tipo_documento')->label('Tipo')->badge()
                    ->formatStateUsing(fn (TipoDocumento $state): string => $state->label()),
                TextColumn::make('estado')->label('Estado')->badge()
                    ->color(fn (EstadoDocumentoFiscal $state): string => match ($state) {
                        EstadoDocumentoFiscal::EMITIDO => 'success',
                        EstadoDocumentoFiscal::RECHAZADO => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (EstadoDocumentoFiscal $state): string => $state->label()),
                TextColumn::make('numero_control')->label('Número de control')->placeholder('—'),
                TextColumn::make('codigo_generacion')->label('Código de generación')->placeholder('—'),
                TextColumn::make('receptor')
                    ->label('Receptor')
                    ->state(fn (DocumentoFiscal $record): ?string => $record->datos_solicitante['nombre'] ?? null),
                TextColumn::make('solicitado_at')->label('Solicitado')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('expires_at')->label('Vence')->dateTime('d/m/Y H:i')->sortable()
                    ->color(fn (DocumentoFiscal $record): string => $record->isSolicitudExpirada() ? 'danger' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('estado')
                    ->options(collect(EstadoDocumentoFiscal::cases())
                        ->mapWithKeys(fn (EstadoDocumentoFiscal $estado): array => [$estado->value => $estado->label()])
                        ->all()),
                SelectFilter::make('tipo_documento')
                    ->options(collect(TipoDocumento::cases())
                        ->mapWithKeys(fn (TipoDocumento $tipo): array => [$tipo->value => $tipo->label()])
                        ->all()),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDocumentosFiscales::route('/'),
        ];
    }
}
