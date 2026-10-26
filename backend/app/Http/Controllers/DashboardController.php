<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class DashboardController extends Controller
{
    /** Solo administrador. Por ahora es un marcador; las tarjetas y el gráfico llegan con DashboardService. */
    public function index(): View
    {
        return view('panel.dashboard');
    }
}
