<?php

namespace App\Contracts;

use App\Models\Pedido;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

interface AuditLoggerInterface
{
    public function record(Pedido $pedido, User $actor, string $type, array $payload = []): void;

    public function recordEntity(Model $entity, User $actor, string $type, array $payload = []): void;
}
