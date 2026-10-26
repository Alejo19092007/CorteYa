<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    /** La validación y el límite de intentos viven en LoginRequest; aquí solo se abre la sesión. */
    public function login(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // Nuevo id de sesión tras el login: evita el robo de sesión (session fixation)
        $request->session()->regenerate();

        return redirect()->intended($request->user()->rutaInicio());
    }

    /** Pantalla de inicio según el rol: admin → dashboard, barbero → su agenda. */
    public function inicio(Request $request): RedirectResponse
    {
        return redirect($request->user()->rutaInicio());
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        // Se invalida la sesión y se renueva el token CSRF
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
