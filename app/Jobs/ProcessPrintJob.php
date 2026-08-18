<?php

namespace App\Jobs;

use App\Services\Printing\EscPosPrintService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcessPrintJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        public readonly int $trabajoId,
    ) {}

    public function handle(EscPosPrintService $printer): void
    {
        $printer->print($this->trabajoId);
    }

    public function failed(\Throwable $e): void
    {
        Log::error("ProcessPrintJob falló definitivamente para trabajo #{$this->trabajoId}: {$e->getMessage()}");

        \App\Models\TrabajoImpresion::where('id', $this->trabajoId)->update([
            'estado' => \App\Enums\EstadoImpresion::ERROR,
            'ultimo_error' => Str::limit($e->getMessage(), 500),
        ]);
    }
}
