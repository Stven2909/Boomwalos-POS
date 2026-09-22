<?php

namespace App\Jobs;

use App\Models\Impresora;
use App\Services\Gaveta\PulseGavetaDriver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RegistradoraAbrirJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $impresoraId,
    ) {}

    public function handle(PulseGavetaDriver $gaveta): void
    {
        $impresora = Impresora::query()
            ->whereKey($this->impresoraId)
            ->where('activa', true)
            ->first();

        if (! $impresora) {
            Log::warning("RegistradoraAbrirJob: la impresora #{$this->impresoraId} no existe o está inactiva.");

            return;
        }

        try {
            $gaveta->abrir($impresora);
        } catch (Throwable $e) {
            Log::warning("RegistradoraAbrirJob: no se pudo abrir la gaveta: {$e->getMessage()}");
        }
    }
}
