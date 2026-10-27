<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TurnoController;
use Illuminate\Support\Facades\Route;

// Solo para quien NO ha iniciado sesión (si ya la tiene, el middleware "guest" lo redirige a inicio)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
});

// Solo para quien SÍ inició sesión (si no, "auth" lo manda a /login)
Route::middleware('auth')->group(function () {
    Route::get('/', [AuthController::class, 'inicio'])->name('inicio');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Solo el dueño
    Route::middleware('role:admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Estas dos van ANTES del resource: si no, "/turnos/disponibilidad" se leería como un {turno}
        Route::get('/turnos/disponibilidad', [TurnoController::class, 'disponibilidad'])->name('turnos.disponibilidad');
        Route::get('/clientes/buscar', [ClienteController::class, 'buscar'])->name('clientes.buscar');

        Route::resource('turnos', TurnoController::class);
    });

    // Solo los barberos
    Route::middleware('role:barbero')->group(function () {
        Route::get('/agenda', [TurnoController::class, 'agenda'])->name('agenda');
    });

    // Admin y barbero: confirmar / atendido / no asistió. La política limita al barbero a sus turnos.
    Route::patch('/turnos/{turno}/estado', [TurnoController::class, 'cambiarEstado'])
        ->middleware('can:cambiarEstado,turno')
        ->name('turnos.estado');
});

// Solo en desarrollo: para ver las páginas de error propias sin provocar el error de verdad.
// Ej.: http://localhost:8000/prueba-error/500
if (app()->environment('local')) {
    Route::get('/prueba-error/{codigo}', fn (int $codigo) => abort($codigo))
        ->whereIn('codigo', [403, 404, 419, 500])
        ->name('prueba.error');
}
