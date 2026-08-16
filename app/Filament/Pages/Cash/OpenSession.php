<?php

namespace App\Filament\Pages\Cash;

use App\Filament\Pages\Pos\ServiceSelection;
use App\Models\Establecimiento;
use App\Models\EventoAuditoria;
use App\Models\SesionCaja;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            'montoInicial' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
        ], [
            'montoInicial.required' => 'Escribe el monto inicial de caja.',
            'montoInicial.regex' => 'El monto inicial debe ser numérico con hasta dos decimales.',
        ]);

        $establecimientoId = app(\App\Contracts\EstablishmentContextInterface::class)->idOrNull();

        if (! $establecimientoId) {
            throw ValidationException::withMessages([
                'establecimiento' => 'Configura un establecimiento antes de abrir el turno.',
            ]);
        }

        DB::transaction(function () use ($establecimientoId): void {
            Establecimiento::query()
                ->lockForUpdate()
                ->findOrFail($establecimientoId);

            $active = SesionCaja::query()
                ->where('establecimiento_id', $establecimientoId)
                ->whereNull('fecha_cierre')
                ->lockForUpdate()
                ->exists();

            if (! $active) {
                $montoInicial = bcadd($this->montoInicial, '0', 2);

                $sesion = SesionCaja::create([
                    'establecimiento_id' => $establecimientoId,
                    'usuario_apertura_id' => auth()->id(),
                    'monto_inicial' => $montoInicial,
                    'fecha_apertura' => now(),
                ]);

                EventoAuditoria::create([
                    'entidad_tipo' => SesionCaja::class,
                    'entidad_id' => $sesion->getKey(),
                    'usuario_id' => auth()->id(),
                    'tipo_evento' => 'caja_abierta',
                    'payload' => [
                        'monto_inicial' => $montoInicial,
                        'establecimiento_id' => $establecimientoId,
                    ],
                ]);
            }
        });

        $this->redirect(ServiceSelection::getUrl());
    }

    private function activeSession(): ?SesionCaja
    {
        $establishmentId = app(\App\Contracts\EstablishmentContextInterface::class)->idOrNull();

        return $establishmentId
            ? SesionCaja::query()
                ->where('establecimiento_id', $establishmentId)
                ->whereNull('fecha_cierre')
                ->latest('id')
                ->first()
            : null;
    }
}
