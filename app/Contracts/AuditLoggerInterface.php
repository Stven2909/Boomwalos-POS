<?php

namespace App\Contracts;

use App\Models\Pedido;
use App\Models\User;

interface AuditLoggerInterface
{
    public function record(Pedido $pedido, User $actor, string $type, array $payload = []): void;
}
