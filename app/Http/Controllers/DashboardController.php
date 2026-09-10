<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Placeholder authenticated landing page. The real, role-aware dashboard is
     * a later milestone — this only proves the authenticated shell works.
     */
    public function __invoke(Request $request): View
    {
        return view('dashboard');
    }
}
