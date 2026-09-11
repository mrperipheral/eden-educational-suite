<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Support\Portal\ParentPortalAuthorizer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The Parent Portal's landing page — "My Children" (see
 * `docs/parent-portal.md`). Gated `module:parent-portal` **and**
 * `->can('portal.parent')`. Every student shown is resolved through
 * `App\Support\Portal\ParentPortalAuthorizer`, never a raw query the view
 * happens to filter — an unlinked parent or a guardian with no linked
 * students sees a safe empty state, never an error.
 */
class ParentPortalController extends Controller
{
    public function __construct(private readonly ParentPortalAuthorizer $authorizer) {}

    public function index(Request $request): View
    {
        $this->authorize('portal.parent');

        $guardian = $this->authorizer->guardianFor($request->user());

        $students = $guardian
            ? $guardian->students()->ordered()
                ->with(['currentEnrollment' => fn ($q) => $q->with(['session', 'period', 'level', 'arm'])])
                ->get()
            : collect();

        return view('parent.dashboard', [
            'guardian' => $guardian,
            'students' => $students,
        ]);
    }
}
