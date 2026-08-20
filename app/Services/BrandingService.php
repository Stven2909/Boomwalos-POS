<?php

namespace App\Services;

use App\Contracts\BrandingServiceInterface;
use App\Context\TenantContext;

class BrandingService implements BrandingServiceInterface
{
    public const PRIMARY_COLOR = '#6B4E63';
    public const SECONDARY_COLOR = '#F6F1EE';

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
        return self::PRIMARY_COLOR;
    }

    public function secondaryColor(): string
    {
        return self::SECONDARY_COLOR;
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
        if (empty($path)) {
            return asset($fallback);
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, 'storage/') || str_starts_with($path, '/storage/')) {
            return asset(ltrim($path, '/'));
        }

        return asset($path);
    }
}
