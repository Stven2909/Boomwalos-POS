<?php

namespace App\Application\Printing;

use App\Models\SesionCaja;
use App\Models\User;

class RenderCorteCaja
{
    private const TZ = 'America/El_Salvador';

    public function render(SesionCaja $sesion, ?User $actor): string
    {
        $lineas = [];

        $lineas[] = mb_strtoupper((string) ($sesion->establecimiento?->nombre ?? 'POS'));
        $lineas[] = 'CORTE DE CAJA';
        $lineas[] = 'TURNO #'.$sesion->getKey();
        $lineas[] = 'Fecha: '.now()->setTimezone(self::TZ)->format('d/m/Y H:i');
        $lineas[] = str_repeat('-', 32);

        $lineas[] = 'Monto inicial      $'.number_format((float) $sesion->monto_inicial, 2);
        $lineas[] = 'Ventas efectivo    $'.number_format((float) $sesion->total_efectivo, 2);
        $lineas[] = 'Ventas tarjeta     $'.number_format((float) $sesion->total_tarjeta, 2);
        $lineas[] = 'TOTAL VENTAS       $'.number_format((float) $sesion->total_ventas, 2);
        $lineas[] = 'Efectivo esperado  $'.number_format((float) $sesion->efectivo_esperado, 2);
        $lineas[] = 'Efectivo contado   $'.number_format((float) $sesion->efectivo_contado, 2);
        $lineas[] = 'DIFERENCIA         $'.number_format((float) $sesion->diferencia, 2);

        $lineas[] = str_repeat('-', 32);
        $lineas[] = 'Apertura: '.($sesion->usuarioApertura?->getFilamentName() ?? '');
        $lineas[] = 'Cierre:   '.($sesion->usuarioCierre?->getFilamentName() ?? ($actor?->getFilamentName() ?? ''));
        $lineas[] = '';
        $lineas[] = 'CIERRE REGISTRADO';

        return implode("\n", $lineas)."\n";
    }
}
