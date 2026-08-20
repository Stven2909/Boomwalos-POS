<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EstablishmentContextController;
use App\Http\Controllers\Printing\TicketPdfController;

Route::get('/', function () {
    return view('welcome');
});

// Ruta de redirección para evitar error 500 Route [login] not defined en peticiones web no autenticadas
Route::get('/login', function () {
    return redirect()->to('/admin/login');
})->name('login');

Route::post('/admin/context/establishment/{establecimiento}', EstablishmentContextController::class)
    ->middleware('auth')
    ->name('establishment.context');

Route::middleware('auth')->group(function () {
    Route::get('/admin/impresion/trabajo/{trabajo}/pdf', [TicketPdfController::class, 'verTrabajoPdf'])
        ->name('impresion.trabajo.pdf');
    Route::get('/admin/impresion/prueba/{impresora}/pdf', [TicketPdfController::class, 'verPruebaPdf'])
        ->name('impresion.prueba.pdf');
});
