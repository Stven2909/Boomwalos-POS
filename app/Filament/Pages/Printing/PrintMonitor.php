<?php

namespace App\Filament\Pages\Printing;

use App\Enums\EstadoImpresion;
use App\Enums\TipoTrabajoImpresion;
use App\Jobs\ProcessPrintJob;
use App\Models\TrabajoImpresion;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PrintMonitor extends Page
{
    protected static ?string $slug = 'monitor-impresion';

    protected static ?string $title = 'Monitor de Impresión';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Ajustes';

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.admin.pages.printing.print-monitor';

    public string $filterEstado = 'all';

    public string $filterTipo = 'all';

    public ?string $feedback = null;

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('ver_impresoras');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function retryJob(int $jobId): void
    {
        $job = TrabajoImpresion::where('id', $jobId)
            ->where('estado', EstadoImpresion::ERROR)
            ->first();

        if (! $job) {
            $this->feedback = 'El trabajo no se encontró o no está en estado de error.';
            return;
        }

        $job->update(['estado' => EstadoImpresion::PENDIENTE]);
        ProcessPrintJob::dispatch($job->getKey())->afterCommit();

        $this->feedback = "Trabajo #{$job->getKey()} reenviado a la cola de impresión.";
    }

    public function retryAllFailed(): void
    {
        $failedJobs = TrabajoImpresion::where('estado', EstadoImpresion::ERROR)->get();

        foreach ($failedJobs as $job) {
            $job->update(['estado' => EstadoImpresion::PENDIENTE]);
            ProcessPrintJob::dispatch($job->getKey())->afterCommit();
        }

        $count = $failedJobs->count();
        $this->feedback = "{$count} " . ($count === 1 ? 'trabajo reenviado' : 'trabajos reenviados') . ' a la cola de impresión.';
    }

    public function getJobsProperty(): Collection
    {
        return TrabajoImpresion::query()
            ->with(['impresora', 'pedido'])
            ->when($this->filterEstado !== 'all', fn (Builder $q) => $q->where('estado', $this->filterEstado))
            ->when($this->filterTipo !== 'all', fn (Builder $q) => $q->where('tipo_trabajo', $this->filterTipo))
            ->latest()
            ->limit(100)
            ->get();
    }

    public function setFilterEstado(string $estado): void
    {
        $this->filterEstado = $estado;
    }

    public function setFilterTipo(string $tipo): void
    {
        $this->filterTipo = $tipo;
    }

    public function estadoOptions(): array
    {
        return [
            'all' => 'Todos',
            'PENDIENTE' => 'Pendiente',
            'PROCESANDO' => 'Procesando',
            'IMPRESO' => 'Impreso',
            'ERROR' => 'Error',
        ];
    }

    public function tipoOptions(): array
    {
        return [
            'all' => 'Todos',
            'COMANDA' => 'Comanda',
            'TICKET' => 'Ticket',
        ];
    }

    public function failedCount(): int
    {
        return TrabajoImpresion::where('estado', EstadoImpresion::ERROR)->count();
    }
}
