<?php

namespace App\Services\Gaveta;

use App\Contracts\EstablishmentContextInterface;
use App\Enums\TipoImpresora;
use App\Jobs\RegistradoraAbrirJob;
use App\Jobs\RegistradoraCierreJob;
use App\Models\Establecimiento;
use App\Models\EventoAuditoria;
use App\Models\Impresora;
use App\Models\SesionCaja;
use App\Models\User;
use App\Services\ConfiguracionService;
use Illuminate\Support\Facades\Log;
use Throwable;

class RegistradoraSincronizacionService
{
    public const MODO_MANUAL = 'manual';

    public const MODO_AUTO = 'auto-impresora';

    public const CONFIG_MODO = 'gaveta.modo';

    public const CONFIG_EXIGIR = 'gaveta.exigir_confirmacion';

    public const CONFIG_IMPRESORA = 'gaveta.impresora_id';

    public function __construct(
        private readonly ConfiguracionService $configuracion,
        private readonly EstablishmentContextInterface $establishmentContext,
    ) {}

    public function esAutomatico(): bool
    {
        return $this->modo() === self::MODO_AUTO;
    }

    public function modo(): string
    {
        return (string) $this->configuracion->get(self::CONFIG_MODO, self::MODO_MANUAL);
    }

    public function requiereConfirmacionCierre(): bool
    {
        return $this->configuracion->get(self::CONFIG_EXIGIR, true) || ! $this->esAutomatico();
    }

    public function registrarApertura(int $establecimientoId, User $actor, ?int $sesionId = null): array
    {
        $impresora = $this->impresoraGaveta($establecimientoId);

        if ($this->esAutomatico() && $impresora) {
            $this->pulsar($impresora, 'apertura_turno');
            $this->auditar(SesionCaja::class, $sesionId, $actor, 'gaveta_abierta', [
                'motivo' => 'apertura_turno',
                'modo' => $this->modo(),
            ]);

            return ['checkpoint' => false];
        }

        return ['checkpoint' => true];
    }

    public function confirmarApertura(SesionCaja $sesion, User $actor): void
    {
        $this->auditar(SesionCaja::class, $sesion->getKey(), $actor, 'gaveta_abierta', [
            'motivo' => 'apertura_turno',
            'modo' => $this->modo(),
        ]);
    }

    public function registrarCierre(SesionCaja $sesion, User $actor): void
    {
        $this->auditar(SesionCaja::class, $sesion->getKey(), $actor, 'gaveta_cerrada', [
            'modo' => $this->modo(),
        ]);

        $impresora = $this->impresoraGaveta((int) $sesion->establecimiento_id);

        if ($this->esAutomatico() && $impresora) {
            try {
                RegistradoraCierreJob::dispatch($sesion->getKey(), $actor->getKey(), $impresora->getKey());
            } catch (Throwable $e) {
                Log::warning("No se pudo encolar el cierre de la gaveta: {$e->getMessage()}");
            }
        }
    }

    public function abrirManual(int $establecimientoId, User $actor): array
    {
        $sesion = $this->activeSession($establecimientoId);
        $impresora = $this->impresoraGaveta($establecimientoId);

        $entidadTipo = $sesion ? SesionCaja::class : Establecimiento::class;
        $entidadId = $sesion ? $sesion->getKey() : $establecimientoId;

        $this->auditar($entidadTipo, $entidadId, $actor, 'gaveta_abierta', [
            'motivo' => 'manual_punto_venta',
            'modo' => $this->modo(),
        ]);

        if ($this->esAutomatico() && $impresora) {
            $this->pulsar($impresora, 'manual_punto_venta');

            return ['pulso' => true, 'mensaje' => 'La gaveta de dinero se abrió.'];
        }

        return ['pulso' => false, 'mensaje' => 'Abre la gaveta de dinero con la llave física.'];
    }

    public function impresoraGaveta(int $establecimientoId): ?Impresora
    {
        $overrideId = $this->configuracion->get(self::CONFIG_IMPRESORA);

        if ($overrideId && ($override = Impresora::query()->find((int) $overrideId))?->activa) {
            return $override;
        }

        return Impresora::buscar(TipoImpresora::TICKET, $establecimientoId);
    }

    private function pulsar(Impresora $impresora, string $motivo): void
    {
        try {
            RegistradoraAbrirJob::dispatch($impresora->getKey());
        } catch (Throwable $e) {
            Log::warning("No se pudo encolar el pulso de la gaveta ({$motivo}): {$e->getMessage()}");
        }
    }

    private function activeSession(int $establecimientoId): ?SesionCaja
    {
        return SesionCaja::query()
            ->where('establecimiento_id', $establecimientoId)
            ->whereNull('fecha_cierre')
            ->latest('id')
            ->first();
    }

    private function auditar(string $entidadTipo, ?int $entidadId, User $actor, string $tipoEvento, array $payload): void
    {
        try {
            EventoAuditoria::create([
                'entidad_tipo' => $entidadTipo,
                'entidad_id' => $entidadId,
                'usuario_id' => $actor->getKey(),
                'tipo_evento' => $tipoEvento,
                'payload' => $payload,
            ]);
        } catch (Throwable $e) {
            Log::warning("No se pudo auditar {$tipoEvento}: {$e->getMessage()}");
        }
    }
}
