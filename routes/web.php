<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EstablishmentContextController;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/admin/context/establishment/{establecimiento}', EstablishmentContextController::class)
    ->middleware('auth')
    ->name('establishment.context');

Route::middleware('auth')->group(function () {
    Route::get('/admin/impresion/trabajo/{trabajo}/pdf', [\App\Http\Controllers\Printing\TicketPdfController::class, 'verTrabajoPdf'])
        ->name('impresion.trabajo.pdf');
    Route::get('/admin/impresion/prueba/{impresora}/pdf', [\App\Http\Controllers\Printing\TicketPdfController::class, 'verPruebaPdf'])
        ->name('impresion.prueba.pdf');
});
