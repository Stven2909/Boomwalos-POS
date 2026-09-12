<?php

namespace App\Filament\Pages\IT;

use App\Contracts\EstablishmentContextInterface;
use App\Enums\FlujoPos;
use App\Enums\TipoImpresora;
use App\Filament\Concerns\GuardsEstablishment;
use App\Filament\Resources\ConfiguracionFiscal\ConfiguracionFiscalResource;
use App\Filament\Resources\Impresoras\ImpresoraResource;
use App\Filament\Resources\Mesas\MesaResource;
use App\Models\ConfiguracionFiscal;
use App\Models\EventoAuditoria;
use App\Models\Impresora;
use App\Models\Mesa;
use App\Models\SesionCaja;
use App\Services\ConfiguracionFlujosPosService;
use App\Services\PoliticaFlujosPos;
use App\ValueObjects\ConfiguracionFlujosPos;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class PosOperationSettings extends Page
{
    use GuardsEstablishment;

    protected static ?string $navigationLabel = 'Operación del POS';

    protected static ?string $title = 'Operación del POS';

    protected static ?string $slug = 'ti/operacion-pos';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|\UnitEnum|null $navigationGroup = 'TI';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.admin.pages.it.pos-operation-settings';

    public bool $mostradorPrepago = true;

    public bool $mesaPostpago = true;

    public string $predeterminado = FlujoPos::MOSTRADOR_PREPAGO->value;

    public ?string $feedback = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('gestionar_configuracion_pos') ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        if (! $this->ensureEstablishment()) {
            return;
        }

        $this->loadSettings();
    }

    public function toggleMostrador(): void
    {
        if ($this->mostradorPrepago && ! $this->mesaPostpago) {
            $this->feedback = 'Debe permanecer habilitado al menos un flujo del POS.';

            return;
        }

        $this->feedback = null;
        $this->mostradorPrepago = ! $this->mostradorPrepago;

        if (! $this->mostradorPrepago) {
            $this->predeterminado = FlujoPos::MESA_POSTPAGO->value;
        }
    }

    public function toggleMesa(): void
    {
        if ($this->mesaPostpago && ! $this->mostradorPrepago) {
            $this->feedback = 'Debe permanecer habilitado al menos un flujo del POS.';

            return;
        }

        $this->feedback = null;
        $this->mesaPostpago = ! $this->mesaPostpago;

        if (! $this->mesaPostpago) {
            $this->predeterminado = FlujoPos::MOSTRADOR_PREPAGO->value;
        }
    }

    public function save(): void
    {
        try {
            $settings = app(ConfiguracionFlujosPosService::class)->actualizar([
                'version' => ConfiguracionFlujosPos::VERSION,
                'mostrador_prepago' => $this->mostradorPrepago,
                'mesa_postpago' => $this->mesaPostpago,
                'predeterminado' => $this->predeterminado,
            ], auth()->user());

            $this->fillFromSettings($settings);
            $this->feedback = null;

            Notification::make()
                ->title('Operación del POS actualizada')
                ->body('La configuración se aplicó únicamente a la sucursal activa.')
                ->success()
                ->send();
        } catch (ValidationException $exception) {
            $this->feedback = collect($exception->errors())->flatten()->first() ?? 'No se pudo guardar la configuración.';
        } catch (AuthorizationException $exception) {
            abort(403, $exception->getMessage());
        }
    }

    public function getEstablishmentProperty()
    {
        return app(EstablishmentContextInterface::class)->current();
    }

    public function getDependenciesProperty(): array
    {
        $establishmentId = $this->establishment->getKey();

        return [
            'mesas' => Mesa::query()->where('establecimiento_id', $establishmentId)->where('activa', true)->count(),
            'comanda' => Impresora::buscar(TipoImpresora::COMANDA, $establishmentId) !== null,
            'ticket' => Impresora::buscar(TipoImpresora::TICKET, $establishmentId) !== null,
            'fiscal' => ConfiguracionFiscal::query()->where('establecimiento_id', $establishmentId)->where('fiscal_habilitada', true)->exists(),
            'caja' => SesionCaja::query()->where('establecimiento_id', $establishmentId)->whereNull('fecha_cierre')->exists(),
        ];
    }

    public function getHistoryProperty()
    {
        return EventoAuditoria::query()
            ->where('entidad_tipo', get_class($this->establishment))
            ->where('entidad_id', $this->establishment->getKey())
            ->where('tipo_evento', 'configuracion_flujos_pos_actualizada')
            ->with('usuario')
            ->latest('id')
            ->limit(10)
            ->get();
    }

    public function printerUrl(): string
    {
        return ImpresoraResource::getUrl('index');
    }

    public function fiscalUrl(): string
    {
        return ConfiguracionFiscalResource::getUrl('index');
    }

    public function tablesUrl(): string
    {
        return MesaResource::getUrl('index');
    }

    private function loadSettings(): void
    {
        $this->fillFromSettings(app(PoliticaFlujosPos::class)->actual());
    }

    private function fillFromSettings(ConfiguracionFlujosPos $settings): void
    {
        $this->mostradorPrepago = $settings->mostradorPrepago;
        $this->mesaPostpago = $settings->mesaPostpago;
        $this->predeterminado = $settings->predeterminado->value;
    }
}
