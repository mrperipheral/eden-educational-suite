<?php

namespace App\Http\Controllers\Guardian;

use App\Enums\GuardianRelationship;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guardian\GuardianLinkRequest;
use App\Models\Guardian;
use App\Models\GuardianStudent;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The student ↔ guardian relationship. `Student`, `Guardian` and
 * `GuardianStudent` are all `BelongsToSchool`; the ids in the payload are
 * validated to belong to the active school by {@see GuardianLinkRequest}, and
 * the `{link}` route id is resolved with tenant-scoped `findOrFail`. Gated
 * `guardian.manage` + `module:guardians`.
 *
 * A link is created from the student's profile ("Add guardian"); it can be
 * edited (relationship / primary contact) or removed from either profile.
 */
class GuardianLinkController extends Controller
{
    public function create(int $student): View
    {
        $this->authorize('guardian.manage');

        $student = Student::query()->with('guardianLinks')->findOrFail($student);

        $linkedIds = $student->guardianLinks->pluck('guardian_id')->all();

        return view('guardians.link', [
            'student' => $student,
            'guardians' => Guardian::query()->ordered()->get(),
            'linkedGuardianIds' => $linkedIds,
            'relationships' => GuardianRelationship::all(),
            'link' => new GuardianStudent,
        ]);
    }

    public function store(GuardianLinkRequest $request): RedirectResponse
    {
        $student = Student::query()->findOrFail((int) $request->validated('student_id'));

        $link = $student->guardianLinks()->create([
            'guardian_id' => (int) $request->validated('guardian_id'),
            'relationship' => $request->relationship()->value,
            'is_primary' => $request->isPrimary(),
        ]);

        if ($request->isPrimary()) {
            $link->makePrimary();
        }

        return to_route('students.show', $student)
            ->with('status', __('Guardian linked.'));
    }

    public function update(GuardianLinkRequest $request, int $link): RedirectResponse
    {
        $link = GuardianStudent::query()->findOrFail($link);

        $link->update([
            'relationship' => $request->relationship()->value,
            'is_primary' => $request->isPrimary(),
        ]);

        if ($request->isPrimary()) {
            $link->makePrimary();
        }

        return back()->with('status', __('Relationship updated.'));
    }

    public function destroy(int $link): RedirectResponse
    {
        $this->authorize('guardian.manage');

        $link = GuardianStudent::query()->findOrFail($link);
        $link->delete();

        return back()->with('status', __('Guardian unlinked.'));
    }
}
