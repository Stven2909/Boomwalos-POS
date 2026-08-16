<?php

namespace App\Services;

use App\Contracts\AuditLoggerInterface;
use App\Models\EventoAuditoria;
use App\Models\Pedido;
use App\Models\User;

class AuditLogger implements AuditLoggerInterface
{
    public function record(Pedido $pedido, User $actor, string $type, array $payload = []): void
    {
        EventoAuditoria::create([
            'entidad_tipo' => Pedido::class,
            'entidad_id' => $pedido->getKey(),
            'usuario_id' => $actor->getKey(),
            'tipo_evento' => $type,
            'payload' => $payload,
        ]);
    }
}
