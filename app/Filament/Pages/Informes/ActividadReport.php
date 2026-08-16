<?php

namespace App\Filament\Pages\Informes;

use App\Contracts\EstablishmentContextInterface;
use App\Services\ReportesService;
use Carbon\Carbon;
use Filament\Pages\Page;

class ActividadReport extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'Informes';

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'informes/actividad';

    protected static ?string $title = 'Registro de Actividad';

    protected string $view = 'filament.admin.pages.informes.actividad-report';

    public string $fechaInicio = '';

    public string $fechaFin = '';

    public ?int $establecimientoId = null;

    public ?int $usuarioId = null;

    public ?string $tipoEvento = null;

    public array $eventos = [];

    public int $paginaActual = 1;

    public int $totalPaginas = 1;

    public int $totalEventos = 0;

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
        $this->paginaActual = 1;
        $this->loadPage();
    }

    public function loadPage(): void
    {
        $desde = Carbon::parse($this->fechaInicio)->startOfDay();
        $hasta = Carbon::parse($this->fechaFin)->endOfDay();

        $paginator = app(ReportesService::class)
            ->actividad($desde, $hasta, $this->establecimientoId, $this->usuarioId, $this->tipoEvento, $this->paginaActual);

        $this->eventos = $paginator->items();
        $this->totalPaginas = $paginator->lastPage();
        $this->totalEventos = $paginator->total();
    }

    public function goToPage(int $page): void
    {
        $this->paginaActual = $page;
        $this->loadPage();
    }
}
