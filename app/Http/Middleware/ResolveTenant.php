<?php

namespace App\Http\Middleware;

use App\Contracts\TenantConnectionResolverInterface;
use App\Contracts\EstablishmentContextInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(
        private readonly TenantConnectionResolverInterface $resolver,
        private readonly EstablishmentContextInterface $establishmentContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('platform') || $request->is('platform/*')) {
            $this->establishmentContext->reset();

            return $next($request);
        }

        $tenant = $this->resolver->resolve($request->getHost());

        if (! $tenant && config('tenancy.mode') === 'database') {
            abort(404, 'Empresa no encontrada.');
        }

        if ($tenant) {
            $this->resolver->useTenant($tenant);
        }

        try {
            return $next($request);
        } finally {
            $this->resolver->reset();
            $this->establishmentContext->reset();
        }
    }
}
