<?php

namespace App\Services\Orders;

use Illuminate\Support\Facades\DB;

class PedidoNumberService
{
    public function nextShortCode(int $establishmentId): int
    {
        $fecha = now()->toDateString();

        $secuencia = DB::table('secuencias_pedidos')
            ->where('establecimiento_id', $establishmentId)
            ->where('fecha', $fecha)
            ->lockForUpdate()
            ->first();

        if ($secuencia) {
            DB::table('secuencias_pedidos')->where('id', $secuencia->id)->increment('ultimo_valor');
            $secuencia = DB::table('secuencias_pedidos')->find($secuencia->id);
        } else {
            DB::table('secuencias_pedidos')->insert([
                'establecimiento_id' => $establishmentId,
                'fecha' => $fecha,
                'ultimo_valor' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $secuencia = DB::table('secuencias_pedidos')
                ->where('establecimiento_id', $establishmentId)
                ->where('fecha', $fecha)
                ->first();
        }

        return (int) $secuencia->ultimo_valor;
    }

    public function nextTracking(): string
    {
        do {
            $tracking = 'POS-'.now()->format('ymdHis').'-'.strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        } while (\App\Models\Pedido::query()->where('numero_seguimiento', $tracking)->exists());

        return $tracking;
    }

    /**
     * Devuelve el último código corto consumido por un borrador eliminado.
     * Solo lo hace si nadie más lo está usando, para no repetir números.
     */
    public function releaseLastCode(int $establishmentId, ?int $codigo, ?string $fecha): void
    {
        if ($codigo === null || $fecha === null) {
            return;
        }

        $secuencia = DB::table('secuencias_pedidos')
            ->where('establecimiento_id', $establishmentId)
            ->where('fecha', $fecha)
            ->lockForUpdate()
            ->first();

        if (! $secuencia || (int) $secuencia->ultimo_valor !== $codigo) {
            return;
        }

        $enUso = DB::table('pedidos')
            ->where('establecimiento_id', $establishmentId)
            ->where('fecha_codigo', $fecha)
            ->where('codigo_corto', $codigo)
            ->exists();

        if ($enUso) {
            return;
        }

        DB::table('secuencias_pedidos')
            ->where('id', $secuencia->id)
            ->update([
                'ultimo_valor' => max(0, $codigo - 1),
                'updated_at' => now(),
            ]);
    }
}
