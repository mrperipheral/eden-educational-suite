<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Support\Portal\ParentPortalAuthorizer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The signed-in parent's own guardian profile (see `docs/parent-portal.md`
 * §"Account vs guardian profile"). Deliberately **read-only**: the M10
 * `Guardian` record stays school-managed ("M10 remains the source of
 * truth"); a parent who needs a detail corrected contacts the school. Login
 * identity (name/email/password) is the `User` account, edited at the
 * existing `/settings/profile` — never duplicated here.
 */
class ParentProfileController extends Controller
{
    public function __construct(private readonly ParentPortalAuthorizer $authorizer) {}

    public function edit(Request $request): View
    {
        $this->authorize('portal.parent');

        return view('parent.profile.edit', [
            'guardian' => $this->authorizer->guardianFor($request->user()),
        ]);
    }
}
