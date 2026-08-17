<?php

namespace App\Filament\Pages;

use App\Context\TenantContext;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class BrandSettings extends Page
{
    protected static ?string $navigationLabel = 'Marca de la empresa';

    protected static ?string $title = 'Marca de la empresa';

    protected static ?string $slug = 'marca';

    protected static string|\BackedEnum|null $navigationIcon = \Filament\Support\Icons\Heroicon::OutlinedPaintBrush;

    protected static string|\UnitEnum|null $navigationGroup = 'Administración';

    protected string $view = 'filament.admin.pages.brand-settings';

    public string $displayName = '';

    public string $logoPath = '';

    public string $faviconPath = '';

    public string $primaryColor = '#6B4E63';

    public string $secondaryColor = '#F6F1EE';

    public string $ticketHeader = '';

    public string $ticketFooter = '';

    public string $contactPhone = '';

    public string $contactEmail = '';

    public bool $editing = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('gestionar_marca') ?? false;
    }

    public function mount(): void
    {
        $tenant = app(TenantContext::class)->current();

        if (! $tenant) {
            $this->redirect(Dashboard::getUrl());

            return;
        }

        $this->displayName = (string) $tenant->display_name;
        $this->logoPath = (string) ($tenant->logo_path ?? '');
        $this->faviconPath = (string) ($tenant->favicon_path ?? '');
        $this->primaryColor = (string) ($tenant->primary_color ?: '#6B4E63');
        $this->secondaryColor = (string) ($tenant->secondary_color ?: '#F6F1EE');
        $this->ticketHeader = (string) ($tenant->ticket_header ?? '');
        $this->ticketFooter = (string) ($tenant->ticket_footer ?? '');
        $this->contactPhone = (string) ($tenant->contact_phone ?? '');
        $this->contactEmail = (string) ($tenant->contact_email ?? '');
    }

    public function startEditing(): void
    {
        $this->editing = true;
        $this->resetValidation();
    }

    public function cancelEditing(): void
    {
        $this->editing = false;
        $this->resetValidation();
        $this->mount();
    }

    public function save(): void
    {
        $this->validate([
            'displayName' => ['required', 'string', 'max:150'],
            'logoPath' => ['nullable', 'string', 'max:255'],
            'faviconPath' => ['nullable', 'string', 'max:255'],
            'primaryColor' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondaryColor' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'ticketHeader' => ['nullable', 'string', 'max:150'],
            'ticketFooter' => ['nullable', 'string', 'max:1000'],
            'contactPhone' => ['nullable', 'string', 'max:40'],
            'contactEmail' => ['nullable', 'email', 'max:150'],
        ]);

        app(TenantContext::class)->require()->update([
            'display_name' => $this->displayName,
            'logo_path' => $this->logoPath ?: null,
            'favicon_path' => $this->faviconPath ?: null,
            'primary_color' => $this->primaryColor,
            'secondary_color' => $this->secondaryColor,
            'ticket_header' => $this->ticketHeader ?: null,
            'ticket_footer' => $this->ticketFooter ?: null,
            'contact_phone' => $this->contactPhone ?: null,
            'contact_email' => $this->contactEmail ?: null,
        ]);

        Notification::make()->title('Marca actualizada')->success()->send();

        $this->editing = false;
    }
}
