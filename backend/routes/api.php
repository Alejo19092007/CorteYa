<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BarberoApiController;
use App\Http\Controllers\Api\ServicioApiController;
use App\Http\Controllers\Api\TurnoApiController;
use Illuminate\Support\Facades\Route;

// Todas las rutas de este archivo llevan el prefijo /api

// Pública. Limitada a 10 intentos por minuto para frenar la adivinación de contraseñas.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('api.login');

// Con token Sanctum (sin él: 401) y de rol administrador (otro rol: 403)
Route::middleware(['auth:sanctum', 'role:admin'])->name('api.')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::apiResource('servicios', ServicioApiController::class)->except('show');

    Route::get('/barberos', [BarberoApiController::class, 'index'])->name('barberos.index');
    Route::get('/barberos/{barbero}/disponibilidad', [BarberoApiController::class, 'disponibilidad'])->name('barberos.disponibilidad');

    Route::apiResource('turnos', TurnoApiController::class)->except('show');
});
