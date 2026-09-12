<?php

namespace App\Services;

use App\Contracts\BrandingServiceInterface;
use App\Contracts\EstablishmentContextInterface;
use App\Models\Configuracion;

class BrandingService implements BrandingServiceInterface
{
    public const PRIMARY_COLOR = '#6B4E63';
    public const SECONDARY_COLOR = '#F6F1EE';

    public function __construct(private readonly EstablishmentContextInterface $establishmentContext) {}

    public function displayName(): string
    {
        return (string) ($this->branding()['display_name'] ?? $this->establishmentContext->currentOrNull()?->nombre ?? config('app.name', 'POS'));
    }

    public function ticketName(): string
    {
        return (string) ($this->branding()['ticket_header'] ?? $this->displayName());
    }

    public function logoUrl(): string
    {
        return $this->assetOrDefault($this->branding()['logo_path'] ?? null, 'images/favicon.png');
    }

    public function faviconUrl(): string
    {
        return $this->assetOrDefault($this->branding()['favicon_path'] ?? null, 'images/favicon.png');
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
        $footer = $this->branding()['ticket_footer'] ?? null;

        return $footer !== null ? (string) $footer : null;
    }

    private function branding(): array
    {
        $establishmentId = $this->establishmentContext->idOrNull();

        if ($establishmentId === null) {
            return [];
        }

        $value = Configuracion::query()
            ->where('establecimiento_id', $establishmentId)
            ->where('clave', 'marca')
            ->value('valor');

        return is_array($value) ? $value : [];
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
