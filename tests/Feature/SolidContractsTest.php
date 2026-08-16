<?php

namespace Tests\Feature;

use App\Application\Fiscal\HttpFiscalGateway;
use App\Contracts\AuditLoggerInterface;
use App\Contracts\CustomerTicketDispatcherInterface;
use App\Contracts\EstablishmentContextInterface;
use App\Contracts\FiscalGatewayInterface;
use App\Contracts\KitchenDispatcherInterface;
use App\Contracts\TenantConnectionResolverInterface;
use App\Services\AuditLogger;
use App\Services\Platform\TenantConnectionResolver;
use Tests\TestCase;

class SolidContractsTest extends TestCase
{
    public function test_external_adapters_are_resolved_through_small_contracts(): void
    {
        $this->assertInstanceOf(HttpFiscalGateway::class, app(FiscalGatewayInterface::class));
        $this->assertInstanceOf(KitchenDispatcherInterface::class, app(KitchenDispatcherInterface::class));
        $this->assertInstanceOf(CustomerTicketDispatcherInterface::class, app(CustomerTicketDispatcherInterface::class));
        $this->assertInstanceOf(AuditLogger::class, app(AuditLoggerInterface::class));
        $this->assertInstanceOf(TenantConnectionResolver::class, app(TenantConnectionResolverInterface::class));
        $this->assertInstanceOf(EstablishmentContextInterface::class, app(EstablishmentContextInterface::class));
    }
}
