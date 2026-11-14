<?php

namespace App\Jobs;

use App\Application\Fiscal\FiscalClient;
use App\Models\ConfiguracionFiscal;
use App\Models\PortalSnapshotOutbox;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncPortalPedidoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public readonly int $outboxId) {}

    public function handle(FiscalClient $client): void
    {
        $outbox = PortalSnapshotOutbox::query()->find($this->outboxId);

        if ($outbox === null || $outbox->estado === 'SINCRONIZADO') {
            return;
        }

        $claimed = DB::transaction(function (): bool {
            $row = PortalSnapshotOutbox::query()->whereKey($this->outboxId)->lockForUpdate()->first();

            if ($row === null || $row->estado === 'SINCRONIZADO') {
                return false;
            }

            if ($row->proximo_intento_at !== null && $row->proximo_intento_at->isFuture()) {
                return false;
            }

            if ($row->estado === 'PROCESANDO'
                && $row->bloqueado_at !== null
                && $row->bloqueado_at->gt(now()->subMinutes(10))) {
                return false;
            }

            $row->forceFill([
                'estado' => 'PROCESANDO',
                'intentos' => $row->intentos + 1,
                'bloqueado_at' => now(),
            ])->save();

            return true;
        });

        if (! $claimed) {
            return;
        }

        $outbox->refresh();

        try {
            $configuracion = ConfiguracionFiscal::query()
                ->where('establecimiento_id', $outbox->establecimiento_id)
                ->first();

            if ($configuracion === null) {
                throw new \RuntimeException('Falta la configuración fiscal para autenticar el snapshot.');
            }

            $client->enviarSnapshotPortalPedido($configuracion, $outbox->payload);

            $outbox->forceFill([
                'estado' => 'SINCRONIZADO',
                'sincronizado_at' => now(),
                'proximo_intento_at' => null,
                'bloqueado_at' => null,
                'ultimo_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $attempt = (int) $outbox->intentos;
            $delayMinutes = match (true) {
                $attempt <= 1 => 1,
                $attempt === 2 => 2,
                $attempt === 3 => 5,
                default => 15,
            };

            $outbox->forceFill([
                'estado' => 'PENDIENTE',
                'proximo_intento_at' => now()->addMinutes($delayMinutes),
                'bloqueado_at' => null,
                'ultimo_error' => mb_substr($exception->getMessage(), 0, 4000),
            ])->save();

            Log::warning('Falló la sincronización del snapshot del portal; queda pendiente de reintento.', [
                'outbox_id' => $outbox->getKey(),
                'pedido_id' => $outbox->pedido_id,
                'tracking' => $outbox->tracking,
                'intento' => $attempt,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
