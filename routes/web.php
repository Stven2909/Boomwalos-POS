<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EstablishmentContextController;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/admin/context/establishment/{establecimiento}', EstablishmentContextController::class)
    ->middleware('auth')
    ->name('establishment.context');
