<?php

namespace App\Http\Controllers;

use App\Support\Tenancy\TenantContext;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Placeholder authenticated landing page. Tenant-scoped: it only renders
     * once EnforceTenant has resolved the active school. The real, role-aware
     * dashboard is a later milestone.
     */
    public function __invoke(TenantContext $tenant): View
    {
        return view('dashboard', [
            'school' => $tenant->schoolOrFail(),
        ]);
    }
}
