<?php

namespace App\Filament\Resources\ConfiguracionFiscal\Pages;

use App\Filament\Resources\ConfiguracionFiscal\ConfiguracionFiscalResource;
use App\Models\Establecimiento;
use App\Services\Fiscal\FiscalOnboardingService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

class ManageConfiguracionFiscal extends ManageRecords
{
    protected static string $resource = ConfiguracionFiscalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('onboardingAutomatico')
                ->label('✨ Activar Facturación Automática (Onboarding MH)')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('primary')
                ->modalHeading('Aprovisionamiento y Activación Fiscal Automática')
                ->modalDescription('Configura el emisor y sube el certificado .p12 para generar automáticamente las claves de acceso de tu restaurante.')
                ->modalSubmitActionLabel('⚡ Activar Facturación en la API')
                ->modalWidth('2xl')
                ->schema([
                    Select::make('establecimiento_id')
                        ->label('Establecimiento a vincular')
                        ->options(fn (): array => Establecimiento::query()->orderBy('nombre')->pluck('nombre', 'id')->all())
                        ->searchable()
                        ->native(false)
                        ->required()
                        ->helperText('Selecciona la sucursal o establecimiento que emitirá los DTEs.'),
                    Radio::make('ambiente')
                        ->label('Ambiente de Facturación')
                        ->options([
                            '00' => '🟡 Modo Pruebas / Homologación (00) - Sin validez tributaria',
                            '01' => '🟢 Modo Producción (01) - En Vivo con validez legal ante Hacienda',
                        ])
                        ->default('00')
                        ->required()
                        ->helperText('Selecciona "00" durante la etapa de certificación y capacitación. Cambia a "01" cuando Hacienda te autorice a emitir en vivo.'),
                    TextInput::make('razon_social')
                        ->label('Razón Social / Nombre Comercial')
                        ->placeholder('Ej: Pupusería Boomwalos S.A. de C.V.')
                        ->required()
                        ->maxLength(200),
                    TextInput::make('nit')
                        ->label('NIT del Emisor')
                        ->placeholder('0614-010190-101-1')
                        ->required()
                        ->maxLength(30),
                    TextInput::make('nrc')
                        ->label('NRC del Emisor')
                        ->placeholder('123456-7')
                        ->maxLength(30),
                    TextInput::make('giro')
                        ->label('Giro Comercial')
                        ->placeholder('Venta de pupusas, comida rápida y bebidas')
                        ->maxLength(250),
                    TextInput::make('codigo_establecimiento')
                        ->label('Código Establecimiento MH')
                        ->default('0001')
                        ->required()
                        ->maxLength(10),
                    TextInput::make('codigo_punto_venta')
                        ->label('Código Punto de Venta MH')
                        ->default('001')
                        ->required()
                        ->maxLength(10),
                    FileUpload::make('p12_file')
                        ->label('Certificado Digital (.p12 / .pfx) del Ministerio de Hacienda')
                        ->acceptedFileTypes(['application/x-pkcs12', 'application/pkcs12', 'application/octet-stream'])
                        ->disk('local')
                        ->directory('temp_certs')
                        ->required()
                        ->helperText('Sube el archivo .p12 provisto por el Ministerio de Hacienda.'),
                    TextInput::make('password')
                        ->label('Contraseña del Certificado .p12')
                        ->password()
                        ->revealable()
                        ->required()
                        ->helperText('Clave secreta asignada al archivo .p12 al descargarlo del portal de Hacienda.'),
                ])
                ->action(function (array $data, Action $action): void {
                    $p12Path = $data['p12_file'] ?? null;
                    $p12Base64 = null;

                    if ($p12Path) {
                        $fullPath = Storage::disk('local')->path($p12Path);
                        if (file_exists($fullPath)) {
                            $binary = file_get_contents($fullPath);
                            $p12Base64 = base64_encode($binary);
                            @unlink($fullPath);
                        }
                    }

                    if (! $p12Base64) {
                        Notification::make()
                            ->title('Archivo no encontrado')
                            ->body('No se pudo leer el archivo de certificado .p12 subido.')
                            ->danger()
                            ->send();
                        $action->halt();

                        return;
                    }

                    $data['p12_base64'] = $p12Base64;

                    /** @var FiscalOnboardingService $onboardingService */
                    $onboardingService = app(FiscalOnboardingService::class);
                    $result = $onboardingService->provisionar($data);

                    if ($result['success']) {
                        Notification::make()
                            ->title('¡Facturación Activada!')
                            ->body($result['message'] . " (Client ID: {$result['client_id']})")
                            ->success()
                            ->persistent()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Error de Activación')
                            ->body($result['message'])
                            ->danger()
                            ->persistent()
                            ->send();
                        $action->halt();
                    }
                }),
            CreateAction::make()
                ->label('Configuración Manual')
                ->icon(Heroicon::OutlinedKey),
        ];
    }
}
