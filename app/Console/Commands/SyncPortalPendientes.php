<?php

namespace App\Console\Commands;

use App\Jobs\SyncPortalPedidoJob;
use App\Models\PortalSnapshotOutbox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncPortalPendientes extends Command
{
    protected $signature = 'portal:sync-pendientes {--limite=100 : Máximo de snapshots a encolar}';

    protected $description = 'Reencola snapshots del portal pendientes o con worker interrumpido.';

    public function handle(): int
    {
        $limite = max(1, min(500, (int) $this->option('limite')));
        $ids = PortalSnapshotOutbox::query()
            ->where(function ($query): void {
                $query->where(function ($pending): void {
                    $pending->where('estado', 'PENDIENTE')
                        ->where(fn ($due) => $due->whereNull('proximo_intento_at')->orWhere('proximo_intento_at', '<=', now()));
                })->orWhere(function ($stale): void {
                    $stale->whereIn('estado', ['EN_COLA', 'PROCESANDO'])
                        ->where('bloqueado_at', '<=', now()->subMinutes(10));
                });
            })
            ->orderBy('created_at')
            ->limit($limite)
            ->pluck('id');

        $encolados = 0;

        foreach ($ids as $id) {
            $reclamado = DB::transaction(function () use ($id): bool {
                $row = PortalSnapshotOutbox::query()->whereKey($id)->lockForUpdate()->first();

                if ($row === null || $row->estado === 'SINCRONIZADO') {
                    return false;
                }

                if ($row->estado === 'PENDIENTE'
                    && $row->proximo_intento_at !== null
                    && $row->proximo_intento_at->isFuture()) {
                    return false;
                }

                if (in_array($row->estado, ['EN_COLA', 'PROCESANDO'], true)
                    && ($row->bloqueado_at === null || $row->bloqueado_at->gt(now()->subMinutes(10)))) {
                    return false;
                }

                $row->forceFill(['estado' => 'EN_COLA', 'bloqueado_at' => now()])->save();

                return true;
            });

            if (! $reclamado) {
                continue;
            }

            try {
                SyncPortalPedidoJob::dispatch((int) $id);
                $encolados++;
            } catch (Throwable $exception) {
                PortalSnapshotOutbox::query()->whereKey($id)->update([
                    'estado' => 'PENDIENTE',
                    'proximo_intento_at' => now()->addMinute(),
                    'bloqueado_at' => null,
                    'ultimo_error' => mb_substr($exception->getMessage(), 0, 4000),
                    'updated_at' => now(),
                ]);

                Log::warning('No se pudo encolar un snapshot pendiente del portal.', [
                    'outbox_id' => $id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->info("Snapshots encolados: {$encolados}");

        return self::SUCCESS;
    }
}
