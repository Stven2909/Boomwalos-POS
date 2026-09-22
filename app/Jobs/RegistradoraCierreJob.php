<?php

namespace App\Jobs;

use App\Application\Printing\RenderCorteCaja;
use App\Enums\EstadoImpresion;
use App\Enums\TipoTrabajoImpresion;
use App\Models\EventoAuditoria;
use App\Models\Impresora;
use App\Models\SesionCaja;
use App\Models\TrabajoImpresion;
use App\Models\User;
use App\Services\Gaveta\PulseGavetaDriver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RegistradoraCierreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $sesionCajaId,
        public readonly int $actorId,
        public readonly int $impresoraId,
    ) {}

    public function handle(RenderCorteCaja $renderer, PulseGavetaDriver $gaveta): void
    {
        try {
            $sesion = SesionCaja::query()
                ->with(['establecimiento', 'usuarioApertura', 'usuarioCierre'])
                ->findOrFail($this->sesionCajaId);

            $impresora = Impresora::query()
                ->whereKey($this->impresoraId)
                ->where('activa', true)
                ->first();

            if (! $impresora) {
                Log::warning("RegistradoraCierreJob: la impresora #{$this->impresoraId} no está activa; corte omitido.");

                return;
            }

            $trabajo = TrabajoImpresion::create([
                'impresora_id' => $impresora->getKey(),
                'tipo_trabajo' => TipoTrabajoImpresion::CORTE_CAJA,
                'estado' => EstadoImpresion::PENDIENTE,
                'contenido' => $renderer->render($sesion, User::query()->find($this->actorId)),
            ]);

            ProcessPrintJob::dispatch($trabajo->getKey());

            $gaveta->abrir($impresora);

            EventoAuditoria::create([
                'entidad_tipo' => SesionCaja::class,
                'entidad_id' => $sesion->getKey(),
                'usuario_id' => $this->actorId,
                'tipo_evento' => 'corte_impreso',
                'payload' => [
                    'trabajo_impresion_id' => $trabajo->getKey(),
                    'impresora_id' => $impresora->getKey(),
                ],
            ]);
        } catch (Throwable $e) {
            Log::error("RegistradoraCierreJob falló para la sesión #{$this->sesionCajaId}: {$e->getMessage()}");
        }
    }
}
