<?php

namespace App\Filament\Pages\Cash;

use App\Filament\Pages\Dashboard;
use App\Models\Establecimiento;
use App\Models\SesionCaja;
use App\Services\CierreCajaService;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class CloseSession extends Page
{
    protected static ?string $slug = 'caja/cerrar';

    protected static ?string $title = 'Cerrar turno';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.admin.pages.cash.close-session';

    public string $efectivoContado = '0.00';

    public ?string $feedback = null;

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('cerrar_caja');
    }

    public function mount(): void
    {
        if (! $this->activeSession()) {
            $this->redirect(Dashboard::getUrl());
        }
    }

    public function getEfectivoEsperadoProperty(): string
    {
        $sesion = $this->activeSession();

        return $sesion ? app(CierreCajaService::class)->calcularEsperado($sesion) : '0.00';
    }

    public function getDiferenciaProperty(): string
    {
        $contado = trim($this->efectivoContado);

        if ($contado === '' || ! preg_match('/^\d+(\.\d+)?$/', $contado)) {
            return '0.00';
        }

        return bcsub(bcadd($contado, '0', 2), $this->efectivoEsperado, 2);
    }

    public function closeSession(): void
    {
        $this->validate([
            'efectivoContado' => ['required', 'numeric', 'min:0'],
        ], [
            'efectivoContado.required' => 'Escribe el monto contado en efectivo.',
            'efectivoContado.numeric' => 'El monto contado debe ser numérico.',
            'efectivoContado.min' => 'El monto contado no puede ser negativo.',
        ]);

        $sesion = $this->activeSession();

        if (! $sesion) {
            $this->redirect(Dashboard::getUrl());

            return;
        }

        try {
            app(CierreCajaService::class)->cerrar($sesion, $this->efectivoContado, auth()->user());

            session()->flash('pos_feedback', 'Turno de caja cerrado correctamente.');
            $this->redirect(Dashboard::getUrl());
        } catch (ValidationException|AuthorizationException $exception) {
            $this->feedback = $exception instanceof AuthorizationException
                ? $exception->getMessage()
                : collect($exception->errors())->flatten()->first() ?? 'No se pudo cerrar la caja.';
        }
    }

    public function money(string $amount): string
    {
        return '$'.number_format((float) $amount, 2, '.', ',');
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
