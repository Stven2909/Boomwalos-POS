<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformTenantConnection extends Model
{
    protected $table = 'platform_tenant_connections';

    protected $fillable = [
        'tenant_id',
        'driver',
        'host',
        'port',
        'database',
        'username',
        'password',
        'unix_socket',
        'options',
    ];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'password' => 'encrypted',
            'options' => 'array',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('tenancy.mode') === 'single'
            ? config('tenancy.fallback_connection', config('database.default'))
            : 'platform';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(PlatformTenant::class, 'tenant_id');
    }
}
