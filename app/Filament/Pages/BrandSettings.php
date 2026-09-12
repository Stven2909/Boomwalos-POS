<?php

namespace App\Filament\Pages;

use App\Contracts\EstablishmentContextInterface;
use App\Models\Configuracion;
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
        $context = app(EstablishmentContextInterface::class);
        $establishment = $context->currentOrNull();

        if ($establishment === null) {
            $this->redirect(Dashboard::getUrl());

            return;
        }

        $branding = Configuracion::query()
            ->where('establecimiento_id', $establishment->getKey())
            ->where('clave', 'marca')
            ->value('valor');
        $branding = is_array($branding) ? $branding : [];

        $this->displayName = (string) ($branding['display_name'] ?? $establishment->nombre);
        $this->logoPath = (string) ($branding['logo_path'] ?? '');
        $this->faviconPath = (string) ($branding['favicon_path'] ?? '');
        $this->ticketHeader = (string) ($branding['ticket_header'] ?? '');
        $this->ticketFooter = (string) ($branding['ticket_footer'] ?? '');
        $this->contactPhone = (string) ($branding['contact_phone'] ?? '');
        $this->contactEmail = (string) ($branding['contact_email'] ?? '');
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
            'ticketHeader' => ['nullable', 'string', 'max:150'],
            'ticketFooter' => ['nullable', 'string', 'max:1000'],
            'contactPhone' => ['nullable', 'string', 'max:40'],
            'contactEmail' => ['nullable', 'email', 'max:150'],
        ]);

        $establishmentId = app(EstablishmentContextInterface::class)->id();

        Configuracion::updateOrCreate(
            ['establecimiento_id' => $establishmentId, 'clave' => 'marca'],
            ['valor' => [
                'display_name' => $this->displayName,
                'logo_path' => $this->logoPath ?: null,
                'favicon_path' => $this->faviconPath ?: null,
                'ticket_header' => $this->ticketHeader ?: null,
                'ticket_footer' => $this->ticketFooter ?: null,
                'contact_phone' => $this->contactPhone ?: null,
                'contact_email' => $this->contactEmail ?: null,
            ]],
        );

        Notification::make()->title('Datos de empresa actualizados')->success()->send();

        $this->editing = false;
    }
}
