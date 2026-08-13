<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Kitchen\KitchenDisplay;
use App\Filament\Pages\Pos\EntregaDisplay;
use App\Filament\Pages\Pos\ServiceSelection;
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
            ->brandName('Los Boomwalos')
            ->favicon(asset('images/favicon.png'))
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
                    ->url('#'),
                NavigationItem::make('Cocina')
                    ->icon('heroicon-o-fire')
                    ->url(fn (): string => KitchenDisplay::getUrl())
                    ->visible(fn (): bool => auth()->user()?->can('operar_cocina') ?? false)
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.pages.cocina')),
                NavigationItem::make('Entrega')
                    ->icon('heroicon-o-shopping-bag')
                    ->url(fn (): string => EntregaDisplay::getUrl())
                    ->visible(fn (): bool => auth()->user()?->can('operar_cocina') ?? false)
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.pages.entrega')),
                NavigationItem::make('Clientes')
                    ->icon('heroicon-o-users')
                    ->url('#'),
                NavigationItem::make('Informes')
                    ->icon('heroicon-o-chart-bar')
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
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
