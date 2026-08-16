<?php

namespace App\Services;

use App\Contracts\EstablishmentContextInterface;
use App\Enums\EstadoComercialPedido;
use App\Enums\MetodoPago;
use App\Models\Establecimiento;
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
    public function __construct(private readonly EstablishmentContextInterface $establishmentContext) {}

    public function calcularEsperado(SesionCaja $sesion): string
    {
        return $this->calcularResumen($sesion)['efectivo_esperado'];
    }

    public function calcularResumen(SesionCaja $sesion): array
    {
        $totalEfectivo = '0.00';
        $totalTarjeta = '0.00';

        $pagos = Pago::query()
            ->where('sesion_caja_id', $sesion->getKey())
            ->get();

        foreach ($pagos as $pago) {
            $neto = bcsub((string) $pago->monto_recibido, (string) $pago->cambio_devuelto, 2);

            if ($pago->metodo_pago === MetodoPago::EFECTIVO) {
                $totalEfectivo = bcadd($totalEfectivo, $neto, 2);
            } else {
                $totalTarjeta = bcadd($totalTarjeta, $neto, 2);
            }
        }

        return [
            'monto_inicial' => bcadd((string) $sesion->monto_inicial, '0', 2),
            'total_efectivo' => $totalEfectivo,
            'total_tarjeta' => $totalTarjeta,
            'total_ventas' => bcadd($totalEfectivo, $totalTarjeta, 2),
            'efectivo_esperado' => bcadd((string) $sesion->monto_inicial, $totalEfectivo, 2),
        ];
    }

    public function cerrar(SesionCaja $sesion, string $efectivoContado, User $actor): SesionCaja
    {
        if (! $actor->can('cerrar_caja')) {
            throw new AuthorizationException('No tienes permiso para cerrar la caja.');
        }

        if ($this->establishmentContext->id() !== (int) $sesion->establecimiento_id) {
            throw new AuthorizationException('La caja no pertenece a la sucursal activa.');
        }

        return DB::transaction(function () use ($sesion, $efectivoContado, $actor): SesionCaja {
            Establecimiento::query()
                ->lockForUpdate()
                ->findOrFail($sesion->establecimiento_id);

            $sesion = SesionCaja::query()
                ->lockForUpdate()
                ->findOrFail($sesion->getKey());

            if ($sesion->fecha_cierre !== null) {
                throw ValidationException::withMessages([
                    'sesion' => 'Esta caja ya fue cerrada.',
                ]);
            }

            $this->ensureNoOpenOrders($sesion);

            $contado = $this->normalizeAmount($efectivoContado);
            $resumen = $this->calcularResumen($sesion);
            $diferencia = bcsub($contado, $resumen['efectivo_esperado'], 2);

            $sesion->update([
                'usuario_cierre_id' => $actor->getKey(),
                'total_efectivo' => $resumen['total_efectivo'],
                'total_tarjeta' => $resumen['total_tarjeta'],
                'total_ventas' => $resumen['total_ventas'],
                'efectivo_esperado' => $resumen['efectivo_esperado'],
                'efectivo_contado' => $contado,
                'diferencia' => $diferencia,
                'fecha_cierre' => now(),
            ]);

            $this->audit($sesion, $actor, 'caja_cerrada', [
                'monto_inicial' => $resumen['monto_inicial'],
                'total_efectivo' => $resumen['total_efectivo'],
                'total_tarjeta' => $resumen['total_tarjeta'],
                'total_ventas' => $resumen['total_ventas'],
                'efectivo_esperado' => $resumen['efectivo_esperado'],
                'efectivo_contado' => $contado,
                'diferencia' => $diferencia,
            ]);

            return $sesion->fresh(['usuarioApertura', 'usuarioCierre']);
        });
    }

    private function ensureNoOpenOrders(SesionCaja $sesion): void
    {
        $openOrders = Pedido::query()
            ->where('establecimiento_id', $sesion->establecimiento_id)
            ->where('estado_comercial', EstadoComercialPedido::ABIERTO->value)
            ->count();

        if ($openOrders > 0) {
            $pendientes = $openOrders === 1 ? 'pedido pendiente' : 'pedidos pendientes';

            throw ValidationException::withMessages([
                'pedidos_abiertos' => "No puedes cerrar la caja. Hay {$openOrders} {$pendientes} de cobro. Resuelve esos pedidos antes de cerrar el turno.",
            ]);
        }
    }

    private function normalizeAmount(string $value): string
    {
        $raw = trim($value);

        if ($raw === '' || ! preg_match('/^\d+(\.\d{1,2})?$/', $raw)) {
            throw ValidationException::withMessages([
                'efectivoContado' => 'Escribe un monto contado válido con hasta dos decimales.',
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
