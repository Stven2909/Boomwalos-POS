<?php

namespace App\Contracts;

interface BrandingServiceInterface
{
    public function displayName(): string;

    public function ticketName(): string;

    public function logoUrl(): string;

    public function faviconUrl(): string;

    public function primaryColor(): string;

    public function ticketFooter(): ?string;
}
