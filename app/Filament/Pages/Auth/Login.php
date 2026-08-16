<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Pages\Cash\CloseSession;
use App\Filament\Pages\Cash\OpenSession;
use App\Filament\Pages\EstablishmentSelection;
use App\Filament\Pages\Pos\ServiceSelection;
use App\Models\SesionCaja;
use App\Models\User;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public string $mode = 'cashier';

    public string $codigo = '';

    public string $pin = '';

    public string $email = '';

    public string $password = '';

    public function mount(): void
    {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());
        }
    }

    public function content(Schema $schema): Schema
    {
        $page = $this;

        return $schema
            ->components([
                View::make('filament.admin.auth.login')
                    ->viewData(fn (): array => [
                        'mode' => $page->mode,
                        'codigo' => $page->codigo,
                        'pin' => $page->pin,
                        'email' => $page->email,
                        'password' => $page->password,
                    ]),
            ]);
    }

    public function showAdminLogin(): void
    {
        $this->mode = 'admin';
        $this->resetValidation();
    }

    public function showCashierLogin(): void
    {
        $this->mode = 'cashier';
        $this->resetValidation();
    }

    public function authenticateCashier(string $codigo, string $pin): ?LoginResponse
    {
        $this->codigo = $codigo;
        $this->pin = $pin;

        return $this->authenticate();
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        if ($this->mode === 'admin') {
            $this->validate([
                'email' => ['required', 'email'],
                'password' => ['required', 'string'],
            ]);

            $credentials = [
                'email' => $this->email,
                'password' => $this->password,
            ];
            $role = 'administrador';
            $errorField = 'email';
        } else {
            $this->validate([
                'codigo' => ['required', 'regex:/^\d{2,6}$/'],
                'pin' => ['required', 'digits:4'],
            ], [
                'codigo.regex' => 'El código debe tener entre 2 y 6 dígitos.',
                'pin.digits' => 'El PIN debe tener exactamente 4 dígitos.',
            ]);

            $credentials = [
                'usuario' => $this->codigo,
                'password' => $this->pin,
            ];
            $role = 'cajero';
            $errorField = 'codigo';
        }

        /** @var SessionGuard $authGuard */
        $authGuard = Filament::auth();

        $authenticated = $authGuard->attemptWhen(
            $credentials,
            fn (Authenticatable $user): bool => $user instanceof User && $user->hasRole($role),
            false,
        );

        if (! $authenticated) {
            throw ValidationException::withMessages([
                $errorField => 'Las credenciales no son válidas o el usuario no tiene acceso a este perfil.',
            ]);
        }

        session()->regenerate();

        $this->routeAfterLogin($authGuard->user());

        return app(LoginResponse::class);
    }

    private function routeAfterLogin(User $user): void
    {
        if (app(\App\Contracts\EstablishmentContextInterface::class)->accessible()->count() > 1) {
            session()->put('url.intended', EstablishmentSelection::getUrl());

            return;
        }

        $hasActiveSession = $this->hasActiveCashSession();

        if ($user->hasRole('cajero')) {
            if (! $hasActiveSession) {
                session()->flash('turno_cerrado', true);
                session()->put('url.intended', OpenSession::getUrl());

                return;
            }

            session()->put('url.intended', ServiceSelection::getUrl());

            return;
        }

        session()->flash('turno_cerrado', ! $hasActiveSession);
        session()->put('url.intended', CloseSession::getUrl());
    }

    private function hasActiveCashSession(): bool
    {
        $establishmentId = app(\App\Contracts\EstablishmentContextInterface::class)->id();

        if (! $establishmentId) {
            return false;
        }

        return SesionCaja::query()
            ->where('establecimiento_id', $establishmentId)
            ->whereNull('fecha_cierre')
            ->exists();
    }
}
