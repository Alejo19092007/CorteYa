<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    /** Solo administrador. Los números los calcula DashboardService; aquí solo se entregan a la vista. */
    public function index(): View
    {
        return view('panel.dashboard', $this->dashboard->resumen());
    }
}
