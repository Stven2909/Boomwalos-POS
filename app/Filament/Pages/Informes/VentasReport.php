<?php

namespace App\Filament\Pages\Informes;

use App\Contracts\EstablishmentContextInterface;
use App\Services\ReportesService;
use Carbon\Carbon;
use Filament\Pages\Page;

class VentasReport extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'Informes';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'informes/ventas';

    protected static ?string $title = 'Informe de Ventas';

    protected string $view = 'filament.admin.pages.informes.ventas-report';

    public string $fechaInicio = '';

    public string $fechaFin = '';

    public ?int $establecimientoId = null;

    public array $resumen = ['total_ventas' => 0, 'cantidad_pedidos' => 0, 'ticket_promedio' => 0];

    public array $metodosPago = [];

    public array $topProductos = [];

    public array $ventasSucursal = [];

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

        $this->resumen = $service->ventasResumen($desde, $hasta, $this->establecimientoId);
        $this->metodosPago = $service->ventasPorMetodoPago($desde, $hasta, $this->establecimientoId)->toArray();
        $this->topProductos = $service->topProductos($desde, $hasta, $this->establecimientoId)->toArray();
        $this->ventasSucursal = $service->ventasPorSucursal($desde, $hasta, $this->establecimientoId)->toArray();
    }
}
