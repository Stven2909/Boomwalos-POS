<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Pos\ListaPedidos;
use App\Filament\Pages\Pos\ServiceSelection;
use App\Contracts\BrandingServiceInterface;
use App\Http\Middleware\ResolveTenant;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login(Login::class)
            ->brandName(fn (): string => app(BrandingServiceInterface::class)->displayName())
            ->brandLogo(fn (): ?string => app(BrandingServiceInterface::class)->logoUrl())
            ->brandLogoHeight('2.25rem')
            ->favicon(fn (): string => app(BrandingServiceInterface::class)->faviconUrl())
            ->colors([
                'primary' => '#6B4E63',
                'gray' => Color::Stone,
            ])
            ->font('Manrope')
            ->darkMode(false)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->navigationItems([
                NavigationItem::make('Punto de Venta')
                    ->icon('heroicon-o-calculator')
                    ->url(fn (): string => ServiceSelection::getUrl())
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.pages.pos.*')),
                NavigationItem::make('Pedidos')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->url(fn (): string => ListaPedidos::getUrl()),
                NavigationItem::make('Clientes')
                    ->icon('heroicon-o-users')
                    ->url('#'),

            ])
            ->renderHook(
                PanelsRenderHook::SIDEBAR_FOOTER,
                fn (): string => (string) view('filament.admin.components.sidebar-footer'),
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn (): string => (string) view('filament.admin.components.quick-links'),
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): string => (string) view('filament.admin.components.topbar-actions'),
            )
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): string => (string) view('filament.admin.components.session-info'),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                // ResolveTenant debe quedar antes de SubstituteBindings para que
                // los route model bindings del panel consulten la conexión del
                // tenant ya resuelta (mismo requisito que en el grupo `web`).
                ResolveTenant::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
