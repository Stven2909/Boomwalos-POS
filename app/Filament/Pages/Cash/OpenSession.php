<?php

namespace App\Filament\Pages\Cash;

use App\Filament\Pages\Pos\ServiceSelection;
use App\Models\Establecimiento;
use App\Models\EventoAuditoria;
use App\Models\SesionCaja;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class OpenSession extends Page
{
    protected static ?string $slug = 'caja/abrir';

    protected static ?string $title = 'Abrir turno';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.admin.pages.cash.open-session';

    public string $montoInicial = '0.00';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('abrir_caja');
    }

    public function mount(): void
    {
        if ($this->activeSession()) {
            $this->redirect(ServiceSelection::getUrl());
        }
    }

    public function openSession(): void
    {
        $this->validate([
            'montoInicial' => ['required', 'numeric', 'min:0'],
        ], [
            'montoInicial.required' => 'Escribe el monto inicial de caja.',
            'montoInicial.numeric' => 'El monto inicial debe ser numérico.',
            'montoInicial.min' => 'El monto inicial no puede ser negativo.',
        ]);

        $establecimiento = Establecimiento::query()->orderBy('id')->firstOrFail();

        DB::transaction(function () use ($establecimiento): void {
            $active = SesionCaja::query()
                ->where('establecimiento_id', $establecimiento->getKey())
                ->whereNull('fecha_cierre')
                ->lockForUpdate()
                ->exists();

            if (! $active) {
                $sesion = SesionCaja::create([
                    'establecimiento_id' => $establecimiento->getKey(),
                    'usuario_apertura_id' => auth()->id(),
                    'monto_inicial' => $this->montoInicial,
                    'fecha_apertura' => now(),
                ]);

                EventoAuditoria::create([
                    'entidad_tipo' => SesionCaja::class,
                    'entidad_id' => $sesion->getKey(),
                    'usuario_id' => auth()->id(),
                    'tipo_evento' => 'caja_abierta',
                    'payload' => [
                        'monto_inicial' => $this->montoInicial,
                        'establecimiento_id' => $establecimiento->getKey(),
                    ],
                ]);
            }
        });

        $this->redirect(ServiceSelection::getUrl());
    }

    private function activeSession(): ?SesionCaja
    {
        $establishmentId = Establecimiento::query()->orderBy('id')->value('id');

        return $establishmentId
            ? SesionCaja::query()
                ->where('establecimiento_id', $establishmentId)
                ->whereNull('fecha_cierre')
                ->latest('id')
                ->first()
            : null;
    }
}
