<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Support\Modules\SchoolModules;
use App\Support\Portal\ParentPortalAuthorizer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * One child's profile — the hub a parent reaches every other child-scoped
 * page from (see `docs/parent-portal.md`). `{student}` is never route-model
 * bound; `App\Support\Portal\ParentPortalAuthorizer::authorizedStudent()`
 * resolves it only if this parent is legitimately linked to that child in the
 * active school — a wrong id, another family's child, or a cross-school id
 * all 404 identically.
 */
class ParentStudentController extends Controller
{
    public function __construct(
        private readonly ParentPortalAuthorizer $authorizer,
        private readonly SchoolModules $modules,
    ) {}

    public function show(Request $request, int $student): View
    {
        $this->authorize('portal.parent');

        $user = $request->user();
        $studentModel = $this->authorizer->authorizedStudent($user, $student) ?? abort(404);
        $studentModel->loadMissing(['currentEnrollment' => fn ($q) => $q->with(['session', 'period', 'level', 'arm'])]);

        return view('parent.children.show', [
            'student' => $studentModel,
            'siblings' => $this->authorizer->studentsFor($user),
            'modules' => $this->modules,
        ]);
    }
}
