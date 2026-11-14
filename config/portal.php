<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Carga inicial del portal
    |--------------------------------------------------------------------------
    |
    | Fecha mínima consultable para `portal:backfill`. Si se define, ningún
    | pedido anterior a esa fecha se envía al portal. La ventana además queda
    | acotada a los últimos días del mes de --hasta.
    |
    */

    'corte' => env('PORTAL_CORTE'),

    'backfill_dias' => (int) env('PORTAL_BACKFILL_DIAS', 7),

    /*
    |--------------------------------------------------------------------------
    | Reconciliación
    |--------------------------------------------------------------------------
    |
    | Ventana de órdenes sincronizadas que `portal:reconciliar` vuelve a
    | consultar en ApiFact para copiar estado e identificadores del DTE.
    |
    */

    'reconciliacion_dias' => (int) env('PORTAL_RECONCILIACION_DIAS', 7),

];
