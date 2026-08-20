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
                ->label('Activar Facturación (Onboarding)')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color('primary')
                ->modalHeading('Activación de Facturación Electrónica')
                ->modalDescription('Registra los datos del emisor y sube el certificado de Hacienda para aprovisionar automáticamente la sucursal.')
                ->modalSubmitActionLabel('Guardar y Activar')
                ->modalWidth('2xl')
                ->schema([
                    Select::make('establecimiento_id')
                        ->label('Establecimiento')
                        ->options(fn (): array => Establecimiento::query()->orderBy('nombre')->pluck('nombre', 'id')->all())
                        ->searchable()
                        ->native(false)
                        ->required()
                        ->helperText('Sucursal o establecimiento al que se asociarán los DTEs emitidos.'),
                    Select::make('ambiente')
                        ->label('Ambiente de Facturación')
                        ->options([
                            '00' => 'Pruebas / Homologación (00)',
                            '01' => 'Producción (01) - Oficial MH',
                        ])
                        ->default('00')
                        ->native(false)
                        ->required()
                        ->helperText('Usa "00" durante la etapa de pruebas. Cambia a "01" cuando Hacienda te autorice a emitir en vivo.'),
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
                    TextInput::make('usuario_mh')
                        ->label('Usuario API Hacienda (Opcional)')
                        ->placeholder('Ej: 06140101901011')
                        ->maxLength(50)
                        ->helperText('Usuario para autenticación /auth con el Ministerio de Hacienda.'),
                    TextInput::make('clave_mh')
                        ->label('Contraseña API Hacienda (Opcional)')
                        ->password()
                        ->revealable()
                        ->maxLength(100)
                        ->helperText('Contraseña de la API de Hacienda.'),
                    FileUpload::make('p12_file')
                        ->label('Llave Privada / Certificado (.key / .p12 / .pfx)')
                        ->acceptedFileTypes(['application/x-pkcs12', 'application/pkcs12', 'application/octet-stream', 'application/x-pem-file', 'text/plain'])
                        ->disk('local')
                        ->directory('temp_certs')
                        ->required()
                        ->helperText('Sube tu archivo private_pkcs8.key o certificado .p12 provisto por Hacienda.'),
                    TextInput::make('password')
                        ->label('Contraseña de la Llave Privada / Certificado')
                        ->password()
                        ->revealable()
                        ->required()
                        ->helperText('Contraseña de la llave privada o certificado .p12.'),
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
