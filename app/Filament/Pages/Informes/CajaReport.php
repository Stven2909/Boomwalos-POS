<?php

namespace App\Filament\Pages\Informes;

use App\Contracts\EstablishmentContextInterface;
use App\Services\ReportesService;
use Carbon\Carbon;
use Filament\Pages\Page;

class CajaReport extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'Informes';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'informes/caja';

    protected static ?string $title = 'Informe de Caja';

    protected string $view = 'filament.admin.pages.informes.caja-report';

    public string $fechaInicio = '';

    public string $fechaFin = '';

    public ?int $establecimientoId = null;

    public array $sesiones = [];

    public ?int $sesionSeleccionada = null;

    public array $detallePagos = [];

    public bool $showSucursalFilter = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('ver_reportes') ?? false;
    }

    public function mount(): void
    {
        $this->fechaInicio = now()->subDays(7)->format('Y-m-d');
        $this->fechaFin = now()->format('Y-m-d');
        $this->showSucursalFilter = app(EstablishmentContextInterface::class)->accessible()->count() > 1;
        $this->submitForm();
    }

    public function submitForm(): void
    {
        $desde = Carbon::parse($this->fechaInicio)->startOfDay();
        $hasta = Carbon::parse($this->fechaFin)->endOfDay();

        $this->sesiones = app(ReportesService::class)
            ->sesionesCerradas($desde, $hasta, $this->establecimientoId)
            ->toArray();
        $this->sesionSeleccionada = null;
        $this->detallePagos = [];
    }

    public function selectSesion(int $sesionId): void
    {
        $this->sesionSeleccionada = $sesionId;
        $this->detallePagos = app(ReportesService::class)
            ->sesionDetallePagos($sesionId)
            ->toArray();
    }
}
