<?php

namespace App\Services;

use App\Contracts\AuditLoggerInterface;
use App\Models\EventoAuditoria;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogger implements AuditLoggerInterface
{
    public function record(Pedido $pedido, User $actor, string $type, array $payload = []): void
    {
        $this->recordEntity($pedido, $actor, $type, $payload);
    }

    public function recordEntity(Model $entity, User $actor, string $type, array $payload = []): void
    {
        EventoAuditoria::create([
            'entidad_tipo' => $entity::class,
            'entidad_id' => $entity->getKey(),
            'usuario_id' => $actor->getKey(),
            'tipo_evento' => $type,
            'payload' => $payload,
        ]);
    }
}
