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
}
