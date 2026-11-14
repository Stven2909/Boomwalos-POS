<?php

use App\Console\Commands\EnviarVentasFiscalesPendientes;
use App\Console\Commands\PortalReconciliar;
use App\Console\Commands\ReconciliarFiscales;
use App\Console\Commands\SyncPortalPendientes;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Reintento fiscal automático: respeta el backoff (1→2→5→15 min) registrado
// en proximo_intento_at, por lo que se ejecuta cada minuto y solo envía las
// ventas cuyo reintento ya venció.
Schedule::command(EnviarVentasFiscalesPendientes::class)
    ->everyMinute()
    ->withoutOverlapping();

// Reconciliación por pull: el kiosko local no recibe webhooks, así que
// consulta el estado fiscal de las ventas diferidas por su Código de
// Generación y, al sellarse, reimprime el DTE final.
Schedule::command(ReconciliarFiscales::class)
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Reintenta snapshots del portal que no llegaron a ApiFact, incluso si el
// worker se detuvo después de registrar el cobro.
Schedule::command(SyncPortalPendientes::class)
    ->everyMinute()
    ->withoutOverlapping();

// Reconciliación del portal: copia estado e identificadores del DTE desde
// ApiFact. No emite ni reimprime; la reimpresión sigue siendo solo para
// contingencia o recuperación.
Schedule::command(PortalReconciliar::class)
    ->everyFiveMinutes()
    ->withoutOverlapping();
