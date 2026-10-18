<?php

use App\Console\Commands\EnviarVentasFiscalesPendientes;
use App\Console\Commands\ReconciliarFiscales;
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
