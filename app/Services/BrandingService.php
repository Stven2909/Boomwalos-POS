<?php

namespace App\Services;

use App\Contracts\BrandingServiceInterface;
use App\Context\TenantContext;

class BrandingService implements BrandingServiceInterface
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function displayName(): string
    {
        return $this->tenant()?->display_name ?: 'POS';
    }

    public function ticketName(): string
    {
        return $this->tenant()?->ticket_header ?: $this->displayName();
    }

    public function logoUrl(): string
    {
        return $this->assetOrDefault($this->tenant()?->logo_path, 'images/favicon.png');
    }

    public function faviconUrl(): string
    {
        return $this->assetOrDefault($this->tenant()?->favicon_path, 'images/favicon.png');
    }

    public function primaryColor(): string
    {
        return $this->tenant()?->primary_color ?: '#6B4E63';
    }

    public function secondaryColor(): string
    {
        return $this->tenant()?->secondary_color ?: '#F3EDF2';
    }

    public function ticketFooter(): ?string
    {
        return $this->tenant()?->ticket_footer;
    }

    private function tenant(): mixed
    {
        return $this->tenantContext->current();
    }

    private function assetOrDefault(?string $path, string $fallback): string
    {
        return asset($path ?: $fallback);
    }
}
