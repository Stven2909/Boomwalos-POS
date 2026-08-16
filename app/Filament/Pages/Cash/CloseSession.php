<?php

namespace App\Filament\Pages\Cash;

use App\Filament\Pages\Dashboard;
use App\Models\Establecimiento;
use App\Models\SesionCaja;
use App\Services\CierreCajaService;
use Filament\Facades\Filament;
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
        if (! $this->activeSession() && ! session()->has('turno_cerrado')) {
            $this->redirect(Dashboard::getUrl());
        }
    }

    public function getHasActiveSessionProperty(): bool
    {
        return $this->activeSession() !== null;
    }

    public function getResumenProperty(): array
    {
        $sesion = $this->activeSession();

        return $sesion
            ? app(CierreCajaService::class)->calcularResumen($sesion)
            : [
                'monto_inicial' => '0.00',
                'total_efectivo' => '0.00',
                'total_tarjeta' => '0.00',
                'total_ventas' => '0.00',
                'efectivo_esperado' => '0.00',
            ];
    }

    public function getEfectivoEsperadoProperty(): string
    {
        return $this->resumen['efectivo_esperado'];
    }

    public function getDiferenciaProperty(): string
    {
        $contado = trim($this->efectivoContado);

        if ($contado === '' || ! preg_match('/^\d+(\.\d{1,2})?$/', $contado)) {
            return '0.00';
        }

        return bcsub(bcadd($contado, '0', 2), $this->efectivoEsperado, 2);
    }

    public function closeSession(): void
    {
        $this->validate([
            'efectivoContado' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
        ], [
            'efectivoContado.required' => 'Escribe el monto contado en efectivo.',
            'efectivoContado.regex' => 'El monto contado debe ser numérico con hasta dos decimales.',
        ]);

        $sesion = $this->activeSession();

        if (! $sesion) {
            $this->redirect(Dashboard::getUrl());

            return;
        }

        try {
            app(CierreCajaService::class)->cerrar($sesion, $this->efectivoContado, auth()->user());
        } catch (ValidationException|AuthorizationException $exception) {
            $this->feedback = $exception instanceof AuthorizationException
                ? $exception->getMessage()
                : collect($exception->errors())->flatten()->first() ?? 'No se pudo cerrar la caja.';

            return;
        }

        Filament::auth()->logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect(Filament::getLoginUrl());
    }

    public function money(string $amount): string
    {
        return '$'.number_format((float) $amount, 2, '.', ',');
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
