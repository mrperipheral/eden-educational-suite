<?php

use App\Http\Controllers\Academic\ArmController;
use App\Http\Controllers\Academic\LevelController;
use App\Http\Controllers\Academic\PeriodController;
use App\Http\Controllers\Academic\SessionController as AcademicSessionController;
use App\Http\Controllers\Academic\SubjectController;
use App\Http\Controllers\Assessment\AssessmentCategoryController;
use App\Http\Controllers\Assessment\AssessmentController;
use App\Http\Controllers\Assessment\AssessmentScoreController;
use App\Http\Controllers\Assessment\AssignmentController;
use App\Http\Controllers\Assessment\AssignmentSubmissionController;
use App\Http\Controllers\Attendance\AttendanceRegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Guardian\GuardianController;
use App\Http\Controllers\Guardian\GuardianLinkController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\Platform\SchoolController as PlatformSchoolController;
use App\Http\Controllers\Portal\ParentAssignmentController;
use App\Http\Controllers\Portal\ParentAttendanceController;
use App\Http\Controllers\Portal\ParentPortalController;
use App\Http\Controllers\Portal\ParentProfileController;
use App\Http\Controllers\Portal\ParentReportCardController;
use App\Http\Controllers\Portal\ParentResultController;
use App\Http\Controllers\Portal\ParentStudentController;
use App\Http\Controllers\Portal\ParentTimetableController;
use App\Http\Controllers\Portal\StudentAssignmentController;
use App\Http\Controllers\Portal\StudentAttendanceController;
use App\Http\Controllers\Portal\StudentPortalController;
use App\Http\Controllers\Portal\StudentProfileController;
use App\Http\Controllers\Portal\StudentReportCardController;
use App\Http\Controllers\Portal\StudentResultController;
use App\Http\Controllers\Portal\StudentTimetableController;
use App\Http\Controllers\Results\GradingSchemeController;
use App\Http\Controllers\Results\GradingSchemeGradeController;
use App\Http\Controllers\Results\ReportCardConfigurationController;
use App\Http\Controllers\Results\ReportCardController;
use App\Http\Controllers\Results\ResultAdjustmentController;
use App\Http\Controllers\Results\ResultRunController;
use App\Http\Controllers\Results\ResultWeightingSchemeController;
use App\Http\Controllers\Results\ResultWeightingSchemeItemController;
use App\Http\Controllers\SchoolContextController;
use App\Http\Controllers\SchoolModuleController;
use App\Http\Controllers\SchoolSettingsController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Student\EnrollmentController;
use App\Http\Controllers\Student\StudentController;
use App\Http\Controllers\Teacher\TeacherAssignmentController;
use App\Http\Controllers\Teacher\TeacherController;
use App\Http\Controllers\Timetable\TimetableController;
use App\Http\Controllers\Timetable\TimetableEntryController;
use App\Models\School;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Application health probe for load balancers / uptime monitoring.
// (Laravel's built-in `/up` covers framework boot; this also checks the database.)
Route::get('/health', HealthController::class)->name('health');

/*
| Authenticated — account level (no school context required).
|
|   auth      — must be signed in
|   verified  — must have confirmed their email address
|   active    — account must not be suspended/disabled (checked every request)
*/
Route::middleware(['auth', 'verified', 'active'])->group(function () {
    // Choosing / switching the active school. Runs before a tenant exists.
    Route::get('school', [SchoolContextController::class, 'create'])->name('school-context.create');
    Route::post('school', [SchoolContextController::class, 'store'])->name('school-context.store');

    Route::redirect('settings', 'settings/profile');
    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('settings.profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('settings.profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])
        ->middleware('password.confirm')
        ->name('settings.profile.destroy');

    Route::put('settings/password', [PasswordController::class, 'update'])->name('settings.password.update');

    /*
    | Platform administration — school provisioning. Platform-admin only
    | (SchoolPolicy). NOT tenant-scoped: a school shell is created here; its
    | school-owned data is configured later, inside that school's context.
    */
    Route::middleware('can:viewAny,'.School::class)->prefix('admin')->name('admin.')->group(function () {
        Route::get('schools', [PlatformSchoolController::class, 'index'])->name('schools.index');
        Route::get('schools/create', [PlatformSchoolController::class, 'create'])->name('schools.create');
        Route::post('schools', [PlatformSchoolController::class, 'store'])->name('schools.store');
        Route::get('schools/{school}', [PlatformSchoolController::class, 'show'])->name('schools.show');
    });

    /*
    | Authenticated — tenant scoped. `tenant` resolves the active school into
    | TenantContext or redirects to the school picker.
    */
    Route::middleware('tenant')->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        // Membership & roles for the current school. Permission checks are
        // composed with the active tenant (see docs/authorization.md).
        Route::get('members', [MemberController::class, 'index'])
            ->can('member.view')
            ->name('members.index');
        Route::get('members/create', [MemberController::class, 'create'])
            ->can('member.assign-role')
            ->name('members.create');
        Route::post('members', [MemberController::class, 'store'])
            ->middleware('throttle:10,1')
            ->can('member.assign-role')
            ->name('members.store');
        Route::patch('members/{user}', [MemberController::class, 'updateRole'])->name('members.update-role');
        Route::delete('members/{user}', [MemberController::class, 'destroy'])->name('members.destroy');

        // School configuration — sectioned (see docs/school-settings.md).
        // `school.settings.view` reads; `school.settings.update` writes.
        Route::prefix('settings/school')->name('settings.school.')->group(function () {
            Route::get('/', [SchoolSettingsController::class, 'edit'])
                ->can('school.settings.view')->name('edit');
            Route::patch('/', [SchoolSettingsController::class, 'update'])
                ->can('school.settings.update')->name('update');

            Route::get('branding', [SchoolSettingsController::class, 'branding'])
                ->can('school.settings.view')->name('branding.edit');
            Route::patch('branding', [SchoolSettingsController::class, 'updateBranding'])
                ->can('school.settings.update')->name('branding.update');
            Route::delete('branding/logo', [SchoolSettingsController::class, 'destroyLogo'])
                ->can('school.settings.update')->name('branding.logo.destroy');
            Route::get('branding/logo', [SchoolSettingsController::class, 'showLogo'])
                ->can('school.settings.view')->name('branding.logo.show');

            Route::get('regional', [SchoolSettingsController::class, 'regional'])
                ->can('school.settings.view')->name('regional.edit');
            Route::patch('regional', [SchoolSettingsController::class, 'updateRegional'])
                ->can('school.settings.update')->name('regional.update');

            // Feature / module activation (see docs/module-activation.md).
            Route::get('modules', [SchoolModuleController::class, 'edit'])
                ->can('school.settings.view')->name('modules.edit');
            Route::patch('modules/{module}', [SchoolModuleController::class, 'update'])
                ->can('school.settings.update')->name('modules.update');
        });

        /*
        | Academic foundation — sessions, periods, levels, arms, subjects
        | (see docs/academic-foundation.md). Two gates, both required:
        |   module:academics  — is the feature switched on for this school?
        |   ->can('academics.view' | 'academics.manage')  — may this user?
        | Tenant-owned models are resolved by id in the controller (after the
        | `tenant` middleware) so `SchoolScope` scopes the lookup and another
        | school's id 404s.
        */
        Route::middleware('module:academics')->prefix('academic')->name('academic.')->group(function () {
            // Sessions (years)
            Route::get('sessions', [AcademicSessionController::class, 'index'])
                ->can('academics.view')->name('sessions.index');
            Route::post('sessions', [AcademicSessionController::class, 'store'])
                ->can('academics.manage')->name('sessions.store');
            Route::get('sessions/{session}', [AcademicSessionController::class, 'show'])
                ->whereNumber('session')->can('academics.view')->name('sessions.show');
            Route::get('sessions/{session}/edit', [AcademicSessionController::class, 'edit'])
                ->whereNumber('session')->can('academics.manage')->name('sessions.edit');
            Route::patch('sessions/{session}', [AcademicSessionController::class, 'update'])
                ->whereNumber('session')->can('academics.manage')->name('sessions.update');
            Route::put('sessions/{session}/current', [AcademicSessionController::class, 'makeCurrent'])
                ->whereNumber('session')->can('academics.manage')->name('sessions.current');

            // Periods (terms) within a session
            Route::post('sessions/{session}/periods', [PeriodController::class, 'store'])
                ->whereNumber('session')->can('academics.manage')->name('periods.store');
            Route::get('periods/{period}/edit', [PeriodController::class, 'edit'])
                ->whereNumber('period')->can('academics.manage')->name('periods.edit');
            Route::patch('periods/{period}', [PeriodController::class, 'update'])
                ->whereNumber('period')->can('academics.manage')->name('periods.update');
            Route::put('periods/{period}/current', [PeriodController::class, 'makeCurrent'])
                ->whereNumber('period')->can('academics.manage')->name('periods.current');

            // Levels / classes
            Route::get('levels', [LevelController::class, 'index'])
                ->can('academics.view')->name('levels.index');
            Route::post('levels', [LevelController::class, 'store'])
                ->can('academics.manage')->name('levels.store');
            Route::get('levels/{level}', [LevelController::class, 'show'])
                ->whereNumber('level')->can('academics.view')->name('levels.show');
            Route::get('levels/{level}/edit', [LevelController::class, 'edit'])
                ->whereNumber('level')->can('academics.manage')->name('levels.edit');
            Route::patch('levels/{level}', [LevelController::class, 'update'])
                ->whereNumber('level')->can('academics.manage')->name('levels.update');
            Route::put('levels/{level}/subjects', [LevelController::class, 'syncSubjects'])
                ->whereNumber('level')->can('academics.manage')->name('levels.subjects');

            // Arms / streams within a level
            Route::post('levels/{level}/arms', [ArmController::class, 'store'])
                ->whereNumber('level')->can('academics.manage')->name('arms.store');
            Route::get('arms/{arm}/edit', [ArmController::class, 'edit'])
                ->whereNumber('arm')->can('academics.manage')->name('arms.edit');
            Route::patch('arms/{arm}', [ArmController::class, 'update'])
                ->whereNumber('arm')->can('academics.manage')->name('arms.update');

            // Subjects
            Route::get('subjects', [SubjectController::class, 'index'])
                ->can('academics.view')->name('subjects.index');
            Route::post('subjects', [SubjectController::class, 'store'])
                ->can('academics.manage')->name('subjects.store');
            Route::get('subjects/{subject}/edit', [SubjectController::class, 'edit'])
                ->whereNumber('subject')->can('academics.manage')->name('subjects.edit');
            Route::patch('subjects/{subject}', [SubjectController::class, 'update'])
                ->whereNumber('subject')->can('academics.manage')->name('subjects.update');
        });

        /*
        | Student management (see docs/student-management.md). Two gates:
        |   module:students  — is the feature on for this school?
        |   ->can('student.view' | 'student.manage')  — may this user?
        | Tenant-owned ids resolved by tenant-scoped `findOrFail` in the
        | controller (after `tenant`), so another school's id 404s.
        */
        Route::middleware('module:students')->prefix('students')->name('students.')->group(function () {
            Route::get('/', [StudentController::class, 'index'])
                ->can('student.view')->name('index');
            Route::get('create', [StudentController::class, 'create'])
                ->can('student.manage')->name('create');
            Route::post('/', [StudentController::class, 'store'])
                ->can('student.manage')->name('store');

            // Enrollments — literal prefix so it never collides with {student}.
            Route::get('{student}/enrollments/create', [EnrollmentController::class, 'create'])
                ->whereNumber('student')->can('student.manage')->name('enrollments.create');
            Route::post('{student}/enrollments', [EnrollmentController::class, 'store'])
                ->whereNumber('student')->can('student.manage')->name('enrollments.store');
            Route::get('enrollments/{enrollment}/edit', [EnrollmentController::class, 'edit'])
                ->whereNumber('enrollment')->can('student.manage')->name('enrollments.edit');
            Route::patch('enrollments/{enrollment}', [EnrollmentController::class, 'update'])
                ->whereNumber('enrollment')->can('student.manage')->name('enrollments.update');

            Route::get('{student}', [StudentController::class, 'show'])
                ->whereNumber('student')->can('student.view')->name('show');
            Route::get('{student}/edit', [StudentController::class, 'edit'])
                ->whereNumber('student')->can('student.manage')->name('edit');
            Route::patch('{student}', [StudentController::class, 'update'])
                ->whereNumber('student')->can('student.manage')->name('update');
            Route::patch('{student}/status', [StudentController::class, 'updateStatus'])
                ->whereNumber('student')->can('student.manage')->name('status');
            Route::patch('{student}/user', [StudentController::class, 'updateUser'])
                ->whereNumber('student')->can('student.manage')->name('user');
        });

        /*
        | Guardian / parent management (see docs/guardian-management.md). Two gates:
        |   module:guardians  — is the feature on for this school? (depends on students)
        |   ->can('guardian.view' | 'guardian.manage')  — may this user?
        | Tenant-owned ids ({guardian}, {link}, {student}) are resolved by
        | tenant-scoped `findOrFail` in the controller (after `tenant`), so
        | another school's id 404s.
        */
        Route::middleware('module:guardians')->prefix('guardians')->name('guardians.')->group(function () {
            Route::get('/', [GuardianController::class, 'index'])
                ->can('guardian.view')->name('index');
            Route::get('create', [GuardianController::class, 'create'])
                ->can('guardian.manage')->name('create');
            Route::post('/', [GuardianController::class, 'store'])
                ->can('guardian.manage')->name('store');

            // Student ↔ guardian links — literal prefixes, before {guardian}.
            Route::get('students/{student}/link', [GuardianLinkController::class, 'create'])
                ->whereNumber('student')->can('guardian.manage')->name('links.create');
            Route::post('links', [GuardianLinkController::class, 'store'])
                ->can('guardian.manage')->name('links.store');
            Route::patch('links/{link}', [GuardianLinkController::class, 'update'])
                ->whereNumber('link')->can('guardian.manage')->name('links.update');
            Route::delete('links/{link}', [GuardianLinkController::class, 'destroy'])
                ->whereNumber('link')->can('guardian.manage')->name('links.destroy');

            Route::get('{guardian}', [GuardianController::class, 'show'])
                ->whereNumber('guardian')->can('guardian.view')->name('show');
            Route::get('{guardian}/edit', [GuardianController::class, 'edit'])
                ->whereNumber('guardian')->can('guardian.manage')->name('edit');
            Route::patch('{guardian}', [GuardianController::class, 'update'])
                ->whereNumber('guardian')->can('guardian.manage')->name('update');
            Route::patch('{guardian}/user', [GuardianController::class, 'updateUser'])
                ->whereNumber('guardian')->can('guardian.manage')->name('user');
        });

        /*
        | Teacher management (see docs/teacher-management.md). Two gates:
        |   module:staff  — is the feature on for this school? (depends on academics)
        |   ->can('staff.view' | 'staff.manage')  — may this user?
        | Tenant-owned ids ({teacher}, {assignment}) are resolved by tenant-scoped
        | `findOrFail` in the controller (after `tenant`), so another school's id
        | 404s. A teacher record is separate from a login — `user_id` is optional.
        */
        Route::middleware('module:staff')->prefix('teachers')->name('teachers.')->group(function () {
            Route::get('/', [TeacherController::class, 'index'])
                ->can('staff.view')->name('index');
            Route::get('create', [TeacherController::class, 'create'])
                ->can('staff.manage')->name('create');
            Route::post('/', [TeacherController::class, 'store'])
                ->can('staff.manage')->name('store');

            // Assignments — literal prefix so it never collides with {teacher}.
            Route::get('{teacher}/assignments/create', [TeacherAssignmentController::class, 'create'])
                ->whereNumber('teacher')->can('staff.manage')->name('assignments.create');
            Route::post('{teacher}/assignments', [TeacherAssignmentController::class, 'store'])
                ->whereNumber('teacher')->can('staff.manage')->name('assignments.store');
            Route::get('assignments/{assignment}/edit', [TeacherAssignmentController::class, 'edit'])
                ->whereNumber('assignment')->can('staff.manage')->name('assignments.edit');
            Route::patch('assignments/{assignment}', [TeacherAssignmentController::class, 'update'])
                ->whereNumber('assignment')->can('staff.manage')->name('assignments.update');
            Route::delete('assignments/{assignment}', [TeacherAssignmentController::class, 'destroy'])
                ->whereNumber('assignment')->can('staff.manage')->name('assignments.destroy');

            Route::get('{teacher}', [TeacherController::class, 'show'])
                ->whereNumber('teacher')->can('staff.view')->name('show');
            Route::get('{teacher}/edit', [TeacherController::class, 'edit'])
                ->whereNumber('teacher')->can('staff.manage')->name('edit');
            Route::patch('{teacher}', [TeacherController::class, 'update'])
                ->whereNumber('teacher')->can('staff.manage')->name('update');
            Route::patch('{teacher}/status', [TeacherController::class, 'updateStatus'])
                ->whereNumber('teacher')->can('staff.manage')->name('status');
            Route::patch('{teacher}/user', [TeacherController::class, 'updateUser'])
                ->whereNumber('teacher')->can('staff.manage')->name('user');
        });

        /*
        | Timetable management (see docs/timetable-management.md). Two gates:
        |   module:timetable  — is the feature on? (depends on academics + staff)
        |   ->can('timetable.view' | 'timetable.manage')  — may this user?
        | Tenant-owned ids ({timetable}, {entry}) resolved by tenant-scoped
        | `findOrFail` in the controller (after `tenant`), so another school's id
        | 404s. Every academic / teacher id in a payload is validated to belong to
        | the active school; scheduling clashes are rejected server-side.
        */
        Route::middleware('module:timetable')->prefix('timetables')->name('timetables.')->group(function () {
            Route::get('/', [TimetableController::class, 'index'])
                ->can('timetable.view')->name('index');
            Route::get('create', [TimetableController::class, 'create'])
                ->can('timetable.manage')->name('create');
            Route::post('/', [TimetableController::class, 'store'])
                ->can('timetable.manage')->name('store');
            Route::get('teacher-view', [TimetableController::class, 'teacherView'])
                ->can('timetable.view')->name('teacher');

            // Lessons — literal prefixes so they never collide with {timetable}.
            Route::get('{timetable}/entries/create', [TimetableEntryController::class, 'create'])
                ->whereNumber('timetable')->can('timetable.manage')->name('entries.create');
            Route::post('{timetable}/entries', [TimetableEntryController::class, 'store'])
                ->whereNumber('timetable')->can('timetable.manage')->name('entries.store');
            Route::get('entries/{entry}/edit', [TimetableEntryController::class, 'edit'])
                ->whereNumber('entry')->can('timetable.manage')->name('entries.edit');
            Route::patch('entries/{entry}', [TimetableEntryController::class, 'update'])
                ->whereNumber('entry')->can('timetable.manage')->name('entries.update');
            Route::delete('entries/{entry}', [TimetableEntryController::class, 'destroy'])
                ->whereNumber('entry')->can('timetable.manage')->name('entries.destroy');

            Route::get('{timetable}', [TimetableController::class, 'show'])
                ->whereNumber('timetable')->can('timetable.view')->name('show');
            Route::get('{timetable}/edit', [TimetableController::class, 'edit'])
                ->whereNumber('timetable')->can('timetable.manage')->name('edit');
            Route::patch('{timetable}', [TimetableController::class, 'update'])
                ->whereNumber('timetable')->can('timetable.manage')->name('update');
            Route::patch('{timetable}/status', [TimetableController::class, 'updateStatus'])
                ->whereNumber('timetable')->can('timetable.manage')->name('status');
            Route::delete('{timetable}', [TimetableController::class, 'destroy'])
                ->whereNumber('timetable')->can('timetable.manage')->name('destroy');
        });

        /*
        | Attendance management (see docs/attendance-management.md). Two gates:
        |   module:attendance  — is the feature on? (depends on academics + students,
        |     NOT timetable — attendance works with the timetable module off)
        |   ->can('attendance.view' | '.record' | '.manage')  — may this user?
        | The finer "may this user record for *this* class" rule (a teacher only
        | for an assigned class) is in App\Support\Attendance\AttendanceAuthorizer,
        | checked in the Form Requests / controller. `{register}` is resolved by
        | tenant-scoped `findOrFail` (after `tenant`), so another school's id 404s.
        */
        Route::middleware('module:attendance')->prefix('attendance')->name('attendance.')->group(function () {
            Route::get('/', [AttendanceRegisterController::class, 'index'])
                ->can('attendance.view')->name('index');
            Route::get('create', [AttendanceRegisterController::class, 'create'])
                ->can('attendance.record')->name('create');
            Route::post('/', [AttendanceRegisterController::class, 'store'])
                ->can('attendance.record')->name('store');

            Route::get('{register}', [AttendanceRegisterController::class, 'show'])
                ->whereNumber('register')->can('attendance.view')->name('show');
            Route::patch('{register}/records', [AttendanceRegisterController::class, 'updateRecords'])
                ->whereNumber('register')->can('attendance.record')->name('records');
            Route::post('{register}/submit', [AttendanceRegisterController::class, 'submit'])
                ->whereNumber('register')->can('attendance.record')->name('submit');
            Route::post('{register}/reopen', [AttendanceRegisterController::class, 'reopen'])
                ->whereNumber('register')->can('attendance.manage')->name('reopen');
            Route::delete('{register}', [AttendanceRegisterController::class, 'destroy'])
                ->whereNumber('register')->can('attendance.record')->name('destroy');
        });

        /*
        | Assessment & assignments (see docs/assessment-management.md). Two gates:
        |   module:assessments  — is the feature on? (depends on academics + students,
        |     NOT timetable / attendance / results / cbt)
        |   ->can('assessment.view' | '.record' | '.manage')  — may this user?
        | The finer "may this user record for *this* class + subject" rule (a
        | teacher only for a subject they are assigned to teach that class) is in
        | App\Support\Assessment\AssessmentAuthorizer, checked in the Form
        | Requests / controller. Tenant-owned ids ({assessment}, {assignment},
        | {category}) are resolved by tenant-scoped `findOrFail` (after `tenant`),
        | so another school's id 404s.
        */
        Route::middleware('module:assessments')->prefix('assessments')->name('assessments.')->group(function () {
            // Assessment categories — school configuration.
            Route::get('categories', [AssessmentCategoryController::class, 'index'])
                ->can('assessment.view')->name('categories.index');
            Route::post('categories', [AssessmentCategoryController::class, 'store'])
                ->can('assessment.manage')->name('categories.store');
            Route::patch('categories/{category}', [AssessmentCategoryController::class, 'update'])
                ->whereNumber('category')->can('assessment.manage')->name('categories.update');

            // Assignments — literal prefix, before {assessment}.
            Route::get('assignments', [AssignmentController::class, 'index'])
                ->can('assessment.view')->name('assignments.index');
            Route::get('assignments/create', [AssignmentController::class, 'create'])
                ->can('assessment.record')->name('assignments.create');
            Route::post('assignments', [AssignmentController::class, 'store'])
                ->can('assessment.record')->name('assignments.store');
            Route::get('assignments/{assignment}', [AssignmentController::class, 'show'])
                ->whereNumber('assignment')->can('assessment.view')->name('assignments.show');
            Route::get('assignments/{assignment}/edit', [AssignmentController::class, 'edit'])
                ->whereNumber('assignment')->can('assessment.record')->name('assignments.edit');
            Route::patch('assignments/{assignment}', [AssignmentController::class, 'update'])
                ->whereNumber('assignment')->can('assessment.record')->name('assignments.update');
            Route::delete('assignments/{assignment}', [AssignmentController::class, 'destroy'])
                ->whereNumber('assignment')->can('assessment.record')->name('assignments.destroy');
            Route::get('assignments/{assignment}/submissions', [AssignmentSubmissionController::class, 'edit'])
                ->whereNumber('assignment')->can('assessment.record')->name('assignments.submissions.edit');
            Route::patch('assignments/{assignment}/submissions', [AssignmentSubmissionController::class, 'update'])
                ->whereNumber('assignment')->can('assessment.record')->name('assignments.submissions.update');
            Route::post('assignments/{assignment}/publish', [AssignmentController::class, 'publish'])
                ->whereNumber('assignment')->can('assessment.record')->name('assignments.publish');
            Route::post('assignments/{assignment}/unpublish', [AssignmentController::class, 'unpublish'])
                ->whereNumber('assignment')->can('assessment.record')->name('assignments.unpublish');
            Route::post('assignments/{assignment}/close', [AssignmentController::class, 'close'])
                ->whereNumber('assignment')->can('assessment.record')->name('assignments.close');
            Route::post('assignments/{assignment}/reopen', [AssignmentController::class, 'reopen'])
                ->whereNumber('assignment')->can('assessment.record')->name('assignments.reopen');

            // Assessments.
            Route::get('/', [AssessmentController::class, 'index'])
                ->can('assessment.view')->name('index');
            Route::get('create', [AssessmentController::class, 'create'])
                ->can('assessment.record')->name('create');
            Route::post('/', [AssessmentController::class, 'store'])
                ->can('assessment.record')->name('store');
            Route::get('{assessment}', [AssessmentController::class, 'show'])
                ->whereNumber('assessment')->can('assessment.view')->name('show');
            Route::get('{assessment}/edit', [AssessmentController::class, 'edit'])
                ->whereNumber('assessment')->can('assessment.record')->name('edit');
            Route::patch('{assessment}', [AssessmentController::class, 'update'])
                ->whereNumber('assessment')->can('assessment.record')->name('update');
            Route::delete('{assessment}', [AssessmentController::class, 'destroy'])
                ->whereNumber('assessment')->can('assessment.record')->name('destroy');
            Route::get('{assessment}/scores', [AssessmentScoreController::class, 'edit'])
                ->whereNumber('assessment')->can('assessment.record')->name('scores.edit');
            Route::patch('{assessment}/scores', [AssessmentScoreController::class, 'update'])
                ->whereNumber('assessment')->can('assessment.record')->name('scores.update');
            Route::post('{assessment}/scores/sync', [AssessmentScoreController::class, 'sync'])
                ->whereNumber('assessment')->can('assessment.record')->name('scores.sync');
            Route::post('{assessment}/publish', [AssessmentController::class, 'publish'])
                ->whereNumber('assessment')->can('assessment.record')->name('publish');
            Route::post('{assessment}/unpublish', [AssessmentController::class, 'unpublish'])
                ->whereNumber('assessment')->can('assessment.record')->name('unpublish');
            Route::post('{assessment}/lock', [AssessmentController::class, 'lock'])
                ->whereNumber('assessment')->can('assessment.record')->name('lock');
            Route::post('{assessment}/unlock', [AssessmentController::class, 'unlock'])
                ->whereNumber('assessment')->can('assessment.manage')->name('unlock');
        });

        /*
        | Results & report cards (see docs/results-report-cards.md). Two gates:
        |   module:results  — is the feature on? (depends on assessments, which
        |     itself depends on academics + students — NOT timetable / attendance / cbt)
        |   ->can('result.view' | '.enter' | '.manage' | '.publish' | '.adjust')
        | Compilation only ever reads **locked** M14 assessment scores —
        | App\Services\Results\ResultCompiler, called from ResultRunController.
        | Tenant-owned ids ({scheme}, {grade}, {item}, {run}, {student_result},
        | {subject_result}, {adjustment}) are resolved by tenant-scoped
        | `findOrFail` (after `tenant`), so another school's id 404s.
        */
        Route::middleware('module:results')->prefix('results')->name('results.')->group(function () {
            // Grading schemes.
            Route::get('grading-schemes', [GradingSchemeController::class, 'index'])
                ->can('result.view')->name('grading-schemes.index');
            Route::post('grading-schemes', [GradingSchemeController::class, 'store'])
                ->can('result.manage')->name('grading-schemes.store');
            Route::get('grading-schemes/{scheme}', [GradingSchemeController::class, 'show'])
                ->whereNumber('scheme')->can('result.view')->name('grading-schemes.show');
            Route::patch('grading-schemes/{scheme}', [GradingSchemeController::class, 'update'])
                ->whereNumber('scheme')->can('result.manage')->name('grading-schemes.update');
            Route::post('grading-schemes/{scheme}/grades', [GradingSchemeGradeController::class, 'store'])
                ->whereNumber('scheme')->can('result.manage')->name('grading-schemes.grades.store');
            Route::patch('grading-schemes/grades/{grade}', [GradingSchemeGradeController::class, 'update'])
                ->whereNumber('grade')->can('result.manage')->name('grading-schemes.grades.update');
            Route::delete('grading-schemes/grades/{grade}', [GradingSchemeGradeController::class, 'destroy'])
                ->whereNumber('grade')->can('result.manage')->name('grading-schemes.grades.destroy');

            // Weighting schemes.
            Route::get('weighting-schemes', [ResultWeightingSchemeController::class, 'index'])
                ->can('result.view')->name('weighting-schemes.index');
            Route::post('weighting-schemes', [ResultWeightingSchemeController::class, 'store'])
                ->can('result.manage')->name('weighting-schemes.store');
            Route::get('weighting-schemes/{scheme}', [ResultWeightingSchemeController::class, 'show'])
                ->whereNumber('scheme')->can('result.view')->name('weighting-schemes.show');
            Route::patch('weighting-schemes/{scheme}', [ResultWeightingSchemeController::class, 'update'])
                ->whereNumber('scheme')->can('result.manage')->name('weighting-schemes.update');
            Route::post('weighting-schemes/{scheme}/items', [ResultWeightingSchemeItemController::class, 'store'])
                ->whereNumber('scheme')->can('result.manage')->name('weighting-schemes.items.store');
            Route::patch('weighting-schemes/items/{item}', [ResultWeightingSchemeItemController::class, 'update'])
                ->whereNumber('item')->can('result.manage')->name('weighting-schemes.items.update');
            Route::delete('weighting-schemes/items/{item}', [ResultWeightingSchemeItemController::class, 'destroy'])
                ->whereNumber('item')->can('result.manage')->name('weighting-schemes.items.destroy');

            // Report-card configuration (school-wide, optionally narrowed by
            // ?session=&period= — see ReportCardConfiguration::forScope()).
            Route::get('report-card-configuration', [ReportCardConfigurationController::class, 'edit'])
                ->can('result.view')->name('report-card-configuration.edit');
            Route::patch('report-card-configuration', [ReportCardConfigurationController::class, 'update'])
                ->can('result.manage')->name('report-card-configuration.update');
            Route::post('report-card-configuration/principal-signature', [ReportCardConfigurationController::class, 'updatePrincipalSignature'])
                ->can('result.manage')->name('report-card-configuration.principal-signature.update');
            Route::delete('report-card-configuration/principal-signature', [ReportCardConfigurationController::class, 'destroyPrincipalSignature'])
                ->can('result.manage')->name('report-card-configuration.principal-signature.destroy');
            Route::get('report-card-configuration/principal-signature', [ReportCardConfigurationController::class, 'showPrincipalSignature'])
                ->can('result.view')->name('report-card-configuration.principal-signature.show');
            Route::post('report-card-configuration/class-teacher-signature', [ReportCardConfigurationController::class, 'updateClassTeacherSignature'])
                ->can('result.manage')->name('report-card-configuration.class-teacher-signature.update');
            Route::delete('report-card-configuration/class-teacher-signature', [ReportCardConfigurationController::class, 'destroyClassTeacherSignature'])
                ->can('result.manage')->name('report-card-configuration.class-teacher-signature.destroy');
            Route::get('report-card-configuration/class-teacher-signature', [ReportCardConfigurationController::class, 'showClassTeacherSignature'])
                ->can('result.view')->name('report-card-configuration.class-teacher-signature.show');

            // Result runs.
            Route::get('runs', [ResultRunController::class, 'index'])
                ->can('result.view')->name('runs.index');
            Route::get('runs/create', [ResultRunController::class, 'create'])
                ->can('result.manage')->name('runs.create');
            Route::post('runs', [ResultRunController::class, 'store'])
                ->can('result.manage')->name('runs.store');
            Route::get('runs/{run}', [ResultRunController::class, 'show'])
                ->whereNumber('run')->can('result.view')->name('runs.show');
            Route::delete('runs/{run}', [ResultRunController::class, 'destroy'])
                ->whereNumber('run')->can('result.manage')->name('runs.destroy');
            Route::post('runs/{run}/compile', [ResultRunController::class, 'compile'])
                ->whereNumber('run')->can('result.manage')->name('runs.compile');
            Route::post('runs/{run}/review', [ResultRunController::class, 'review'])
                ->whereNumber('run')->can('result.manage')->name('runs.review');
            Route::post('runs/{run}/approve', [ResultRunController::class, 'approve'])
                ->whereNumber('run')->can('result.publish')->name('runs.approve');
            Route::post('runs/{run}/publish', [ResultRunController::class, 'publish'])
                ->whereNumber('run')->can('result.publish')->name('runs.publish');
            Route::post('runs/{run}/lock', [ResultRunController::class, 'lock'])
                ->whereNumber('run')->can('result.publish')->name('runs.lock');

            Route::patch('runs/{run}/students/{student_result}/comment', [ResultRunController::class, 'updateComment'])
                ->whereNumber(['run', 'student_result'])->can('result.enter')->name('runs.students.comment');
            Route::get('runs/{run}/students/{student_result}/report-card', [ReportCardController::class, 'show'])
                ->whereNumber(['run', 'student_result'])->can('result.view')->name('runs.students.report-card');

            Route::post('runs/{run}/subject-results/{subject_result}/adjustments', [ResultAdjustmentController::class, 'store'])
                ->whereNumber(['run', 'subject_result'])->can('result.adjust')->name('adjustments.store');
            Route::post('runs/{run}/adjustments/{adjustment}/apply', [ResultAdjustmentController::class, 'apply'])
                ->whereNumber(['run', 'adjustment'])->can('result.adjust')->name('adjustments.apply');
            Route::post('runs/{run}/adjustments/{adjustment}/reject', [ResultAdjustmentController::class, 'reject'])
                ->whereNumber(['run', 'adjustment'])->can('result.adjust')->name('adjustments.reject');
        });

        /*
        | Parent Portal (see docs/parent-portal.md). Two gates:
        |   module:parent-portal  — is the feature on? (depends on guardians)
        |   ->can('portal.parent')  — may this user?
        | Every {student} is resolved through
        | App\Support\Portal\ParentPortalAuthorizer::authorizedStudent(), never
        | route-model-bound and never trusted from the URL alone — it 404s
        | unless the signed-in parent is legitimately linked (via their own
        | Guardian record, in the active school) to that exact student.
        | {run} is additionally re-checked against ResultRunStatus::
        | visibleToParents() (published/locked only) before any result or
        | report card is returned.
        */
        Route::middleware('module:parent-portal')->prefix('parent')->name('parent.')->group(function () {
            Route::get('/', [ParentPortalController::class, 'index'])
                ->can('portal.parent')->name('dashboard');

            Route::get('children/{student}', [ParentStudentController::class, 'show'])
                ->whereNumber('student')->can('portal.parent')->name('children.show');

            Route::get('children/{student}/results', [ParentResultController::class, 'index'])
                ->whereNumber('student')->can('portal.parent')->name('results.index');
            Route::get('children/{student}/results/{run}', [ParentResultController::class, 'show'])
                ->whereNumber(['student', 'run'])->can('portal.parent')->name('results.show');

            Route::get('children/{student}/report-cards', [ParentReportCardController::class, 'index'])
                ->whereNumber('student')->can('portal.parent')->name('report-cards.index');
            Route::get('children/{student}/report-cards/{run}', [ParentReportCardController::class, 'show'])
                ->whereNumber(['student', 'run'])->can('portal.parent')->name('report-cards.show');

            Route::get('children/{student}/attendance', [ParentAttendanceController::class, 'index'])
                ->whereNumber('student')->can('portal.parent')->name('attendance.index');

            Route::get('children/{student}/assignments', [ParentAssignmentController::class, 'index'])
                ->whereNumber('student')->can('portal.parent')->name('assignments.index');

            Route::get('children/{student}/timetable', [ParentTimetableController::class, 'index'])
                ->whereNumber('student')->can('portal.parent')->name('timetable.index');

            Route::get('profile', [ParentProfileController::class, 'edit'])
                ->can('portal.parent')->name('profile.edit');
        });

        /*
        | Student Portal (see docs/student-portal.md). Two gates:
        |   module:student-portal  — is the feature on? (depends on students)
        |   ->can('portal.student')  — may this user?
        | A student has at most one linked Student record — resolved via
        | App\Support\Portal\StudentPortalAuthorizer, never trusted from the
        | URL. {run} is re-checked against ResultRunStatus::visibleToParents()
        | (shared with the Parent Portal, M16) before any result/report card
        | is returned.
        */
        Route::middleware('module:student-portal')->prefix('student')->name('student.')->group(function () {
            Route::get('/', [StudentPortalController::class, 'index'])
                ->can('portal.student')->name('dashboard');
            Route::get('profile', [StudentProfileController::class, 'edit'])
                ->can('portal.student')->name('profile.edit');

            Route::get('results', [StudentResultController::class, 'index'])
                ->can('portal.student')->name('results.index');
            Route::get('results/{run}', [StudentResultController::class, 'show'])
                ->whereNumber('run')->can('portal.student')->name('results.show');

            Route::get('report-cards', [StudentReportCardController::class, 'index'])
                ->can('portal.student')->name('report-cards.index');
            Route::get('report-cards/{run}', [StudentReportCardController::class, 'show'])
                ->whereNumber('run')->can('portal.student')->name('report-cards.show');

            Route::get('attendance', [StudentAttendanceController::class, 'index'])
                ->can('portal.student')->name('attendance.index');

            Route::get('assignments', [StudentAssignmentController::class, 'index'])
                ->can('portal.student')->name('assignments.index');

            Route::get('timetable', [StudentTimetableController::class, 'index'])
                ->can('portal.student')->name('timetable.index');
        });
    });
});

require __DIR__.'/auth.php';
