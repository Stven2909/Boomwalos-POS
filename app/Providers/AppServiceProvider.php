<?php

namespace App\Providers;

use App\Contracts\AuditLoggerInterface;
use App\Contracts\BrandingServiceInterface;
use App\Contracts\CustomerTicketDispatcherInterface;
use App\Contracts\EstablishmentContextInterface;
use App\Contracts\FiscalGatewayInterface;
use App\Contracts\KitchenDispatcherInterface;
use App\Contracts\TenantConnectionResolverInterface;
use App\Application\Fiscal\HttpFiscalGateway;
use App\Application\Fiscal\MockFiscalGateway;
use App\Application\Kitchen\QueueKitchenBatch;
use App\Application\Printing\QueueCustomerTicket;
use App\Context\EstablishmentContext;
use App\Context\TenantContext;
use App\Services\AuditLogger;
use App\Services\BrandingService;
use App\Services\Platform\TenantConnectionResolver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->singleton(TenantConnectionResolverInterface::class, TenantConnectionResolver::class);
        $this->app->singleton(EstablishmentContextInterface::class, EstablishmentContext::class);
        $this->app->singleton(BrandingServiceInterface::class, BrandingService::class);

        $this->app->bind(FiscalGatewayInterface::class, function (): FiscalGatewayInterface {
            return config('fiscal.gateway') === 'mock'
                ? new MockFiscalGateway()
                : new HttpFiscalGateway();
        });
        $this->app->bind(KitchenDispatcherInterface::class, QueueKitchenBatch::class);
        $this->app->bind(CustomerTicketDispatcherInterface::class, QueueCustomerTicket::class);
        $this->app->bind(AuditLoggerInterface::class, AuditLogger::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::share('posBranding', $this->app->make(BrandingServiceInterface::class));
    }
}
