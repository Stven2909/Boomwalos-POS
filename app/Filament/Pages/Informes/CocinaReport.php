<?php

namespace App\Filament\Pages\Informes;

use App\Contracts\EstablishmentContextInterface;
use App\Services\ReportesService;
use Carbon\Carbon;
use Filament\Pages\Page;

class CocinaReport extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-fire';

    protected static string|\UnitEnum|null $navigationGroup = 'Informes';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'informes/cocina';

    protected static ?string $title = 'Informe de Cocina';

    protected string $view = 'filament.admin.pages.informes.cocina-report';

    public string $fechaInicio = '';

    public string $fechaFin = '';

    public ?int $establecimientoId = null;

    public array $tiempos = ['pendiente_preparacion' => 0.0, 'preparacion_lista' => 0.0, 'lista_entregada' => 0.0, 'total_completadas' => 0];

    public array $volumen = ['por_estado' => [], 'por_sucursal' => [], 'total_tandas' => 0];

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

        $service = app(ReportesService::class);
        $this->tiempos = $service->cocinaTiemposPromedio($desde, $hasta, $this->establecimientoId);
        $this->volumen = $service->cocinaVolumen($desde, $hasta, $this->establecimientoId);
    }
}
