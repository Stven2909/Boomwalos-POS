<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PlatformTenant extends Model
{
    protected $table = 'platform_tenants';

    protected $fillable = [
        'slug',
        'display_name',
        'status',
        'plan_code',
        'logo_path',
        'favicon_path',
        'primary_color',
        'secondary_color',
        'ticket_header',
        'ticket_footer',
        'contact_phone',
        'contact_email',
    ];

    public function getConnectionName(): ?string
    {
        return config('tenancy.mode') === 'single'
            ? config('tenancy.fallback_connection', config('database.default'))
            : 'platform';
    }

    public function connection(): HasOne
    {
        return $this->hasOne(PlatformTenantConnection::class, 'tenant_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
