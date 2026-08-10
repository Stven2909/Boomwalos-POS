<?php

namespace App\Services;

use App\Enums\EstadoComercialPedido;
use App\Enums\MetodoPago;
use App\Models\EventoAuditoria;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\SesionCaja;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CierreCajaService
{
    public function calcularEsperado(SesionCaja $sesion): string
    {
        $esperado = bcadd((string) $sesion->monto_inicial, '0', 2);

        $pagosEfectivo = Pago::query()
            ->where('metodo_pago', MetodoPago::EFECTIVO)
            ->whereBetween('created_at', [$sesion->fecha_apertura, now()])
            ->whereHas('pedido', function ($query) use ($sesion): void {
                $query->where('establecimiento_id', $sesion->establecimiento_id);
            })
            ->get();

        foreach ($pagosEfectivo as $pago) {
            $neto = bcsub((string) $pago->monto_recibido, (string) $pago->cambio_devuelto, 2);
            $esperado = bcadd($esperado, $neto, 2);
        }

        return $esperado;
    }

    public function cerrar(SesionCaja $sesion, string $efectivoContado, User $actor): SesionCaja
    {
        if (! $actor->can('cerrar_caja')) {
            throw new AuthorizationException('No tienes permiso para cerrar la caja.');
        }

        return DB::transaction(function () use ($sesion, $efectivoContado, $actor): SesionCaja {
            $sesion = SesionCaja::query()
                ->lockForUpdate()
                ->findOrFail($sesion->getKey());

            if ($sesion->fecha_cierre !== null) {
                throw ValidationException::withMessages([
                    'sesion' => 'Esta caja ya fue cerrada.',
                ]);
            }

            $contado = $this->normalizeAmount($efectivoContado);
            $esperado = $this->calcularEsperado($sesion);
            $diferencia = bcsub($contado, $esperado, 2);

            $pedidosAbiertos = Pedido::query()
                ->where('establecimiento_id', $sesion->establecimiento_id)
                ->where('estado_comercial', EstadoComercialPedido::ABIERTO->value)
                ->get(['id', 'numero_seguimiento'])
                ->map(fn (Pedido $pedido): array => [
                    'id' => $pedido->getKey(),
                    'numero_seguimiento' => $pedido->numero_seguimiento,
                ])
                ->values()
                ->all();

            $sesion->update([
                'usuario_cierre_id' => $actor->getKey(),
                'efectivo_esperado' => $esperado,
                'efectivo_contado' => $contado,
                'diferencia' => $diferencia,
                'fecha_cierre' => now(),
            ]);

            $this->audit($sesion, $actor, 'caja_cerrada', [
                'monto_inicial' => (string) $sesion->monto_inicial,
                'efectivo_esperado' => $esperado,
                'efectivo_contado' => $contado,
                'diferencia' => $diferencia,
                'pedidos_abiertos' => $pedidosAbiertos,
            ]);

            return $sesion->fresh(['usuarioApertura', 'usuarioCierre']);
        });
    }

    private function normalizeAmount(string $value): string
    {
        $raw = trim($value);

        if ($raw === '' || ! preg_match('/^\d+(\.\d+)?$/', $raw)) {
            throw ValidationException::withMessages([
                'efectivoContado' => 'Escribe un monto contado válido.',
            ]);
        }

        $amount = bcadd($raw, '0', 2);

        if (bccomp($amount, '0', 2) < 0) {
            throw ValidationException::withMessages([
                'efectivoContado' => 'El monto contado no puede ser negativo.',
            ]);
        }

        return $amount;
    }

    private function audit(SesionCaja $sesion, User $actor, string $tipoEvento, array $payload = []): void
    {
        EventoAuditoria::create([
            'entidad_tipo' => SesionCaja::class,
            'entidad_id' => $sesion->getKey(),
            'usuario_id' => $actor->getKey(),
            'tipo_evento' => $tipoEvento,
            'payload' => $payload,
        ]);
    }
}
