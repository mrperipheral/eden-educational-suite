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
use App\Http\Controllers\Cbt\ExaminationAttemptController;
use App\Http\Controllers\Cbt\ExaminationController;
use App\Http\Controllers\Cbt\ExaminationQuestionController;
use App\Http\Controllers\Cbt\QuestionController;
use App\Http\Controllers\Communication\AnnouncementController;
use App\Http\Controllers\Communication\CommunicationMessageController;
use App\Http\Controllers\Communication\CommunicationStatusController;
use App\Http\Controllers\Communication\CommunicationThreadController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Fees\FeeAdjustmentController;
use App\Http\Controllers\Fees\FeeCategoryController;
use App\Http\Controllers\Fees\FeeChargeController;
use App\Http\Controllers\Fees\FeePaymentController;
use App\Http\Controllers\Fees\FeeStatementController;
use App\Http\Controllers\Fees\FeeStructureController;
use App\Http\Controllers\Guardian\GuardianController;
use App\Http\Controllers\Guardian\GuardianLinkController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\LearningMaterials\LearningMaterialController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaystackWebhookController;
use App\Http\Controllers\Platform\SchoolController as PlatformSchoolController;
use App\Http\Controllers\Portal\ParentAssignmentController;
use App\Http\Controllers\Portal\ParentAttendanceController;
use App\Http\Controllers\Portal\ParentFeeController;
use App\Http\Controllers\Portal\ParentOnlinePaymentController;
use App\Http\Controllers\Portal\ParentPortalController;
use App\Http\Controllers\Portal\ParentProfileController;
use App\Http\Controllers\Portal\ParentReportCardController;
use App\Http\Controllers\Portal\ParentResultController;
use App\Http\Controllers\Portal\ParentStudentController;
use App\Http\Controllers\Portal\ParentTimetableController;
use App\Http\Controllers\Portal\StudentAssignmentController;
use App\Http\Controllers\Portal\StudentAttendanceController;
use App\Http\Controllers\Portal\StudentExamAttemptController;
use App\Http\Controllers\Portal\StudentExaminationController;
use App\Http\Controllers\Portal\StudentFeeController;
use App\Http\Controllers\Portal\StudentLearningMaterialController;
use App\Http\Controllers\Portal\StudentOnlinePaymentController;
use App\Http\Controllers\Portal\StudentPortalController;
use App\Http\Controllers\Portal\StudentProfileController;
use App\Http\Controllers\Portal\StudentReportCardController;
use App\Http\Controllers\Portal\StudentResultController;
use App\Http\Controllers\Portal\StudentTimetableController;
use App\Http\Controllers\Promotion\GraduationController;
use App\Http\Controllers\Promotion\PromotionController;
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

// Paystack webhook (M20, docs/paystack.md) — deliberately outside auth/tenant/
// module entirely; protected by signature verification in the controller,
// which also resolves the owning school from its own stored transaction data
// (never from request input). CSRF-exempted in bootstrap/app.php.
Route::post('/webhooks/paystack', [PaystackWebhookController::class, 'handle'])->name('webhooks.paystack');

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

            // Online payment / Paystack configuration (M20, docs/paystack.md).
            Route::get('payments', [SchoolSettingsController::class, 'payments'])
                ->can('school.settings.view')->name('payments.edit');
            Route::patch('payments', [SchoolSettingsController::class, 'updatePayments'])
                ->can('school.settings.update')->name('payments.update');
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
        | Promotion & Graduation (see docs/promotion.md). Two gates:
        |   module:promotion  — is the feature on for this school? (depends on students)
        |   ->can('promotion.view' | '.manage' | 'graduation.manage')  — may this user?
        | Tenant-owned ids ({batch}, {student}) are resolved by tenant-scoped
        | `findOrFail`, so another school's id 404s. Every academic id in a
        | promotion payload is validated to belong to the active school by
        | `PromoteBatchRequest`/`GraduateBatchRequest`.
        */
        Route::middleware('module:promotion')->prefix('promotion')->name('promotion.')->group(function () {
            Route::get('/', [PromotionController::class, 'index'])
                ->can('promotion.view')->name('index');
            Route::get('create', [PromotionController::class, 'create'])
                ->can('promotion.manage')->name('create');
            Route::get('roster', [PromotionController::class, 'roster'])
                ->can('promotion.manage')->name('roster');
            Route::post('/', [PromotionController::class, 'store'])
                ->can('promotion.manage')->name('store');
            Route::get('{batch}', [PromotionController::class, 'show'])
                ->whereNumber('batch')->can('promotion.view')->name('show');

            // Graduation — literal prefix so it never collides with {batch}.
            Route::get('graduation', [GraduationController::class, 'index'])
                ->can('promotion.view')->name('graduation.index');
            Route::get('graduation/create', [GraduationController::class, 'create'])
                ->can('graduation.manage')->name('graduation.create');
            Route::post('graduation', [GraduationController::class, 'store'])
                ->can('graduation.manage')->name('graduation.store');
            Route::post('graduation/{student}/reactivate', [GraduationController::class, 'reactivate'])
                ->whereNumber('student')->can('graduation.manage')->name('graduation.reactivate');
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
        | Fees & Fee Management (see docs/fees.md). module:fees gates all of
        | it.
        |   ->can('fees.manage')  — categories, structures, charges (config
        |     + raising a charge; never a raw edit of a historical amount).
        |   ->can('fees.view')  — one student's fee statement.
        |   ->can('fees.report')  — the school-wide dashboard/summary.
        |   ->can('fees.record-payment')  — recording a manual payment.
        |   ->can('fees.adjust')  — discount / waive / unwaive / void (a
        |     financial correction, never routine configuration).
        | Tenant-owned ids ({category}, {structure}, {student}, {charge},
        | {payment}) are resolved by tenant-scoped `findOrFail`, so another
        | school's id 404s. Online payment (Paystack) is not built here — M20.
        */
        Route::middleware('module:fees')->prefix('fees')->name('fees.')->group(function () {
            Route::get('/', [FeeStatementController::class, 'index'])
                ->can('fees.report')->name('index');

            Route::prefix('categories')->name('categories.')->group(function () {
                Route::get('/', [FeeCategoryController::class, 'index'])
                    ->can('fees.manage')->name('index');
                Route::post('/', [FeeCategoryController::class, 'store'])
                    ->can('fees.manage')->name('store');
                Route::patch('{category}', [FeeCategoryController::class, 'update'])
                    ->whereNumber('category')->can('fees.manage')->name('update');
            });

            Route::prefix('structures')->name('structures.')->group(function () {
                Route::get('/', [FeeStructureController::class, 'index'])
                    ->can('fees.manage')->name('index');
                Route::get('create', [FeeStructureController::class, 'create'])
                    ->can('fees.manage')->name('create');
                Route::post('/', [FeeStructureController::class, 'store'])
                    ->can('fees.manage')->name('store');
                Route::get('{structure}/edit', [FeeStructureController::class, 'edit'])
                    ->whereNumber('structure')->can('fees.manage')->name('edit');
                Route::patch('{structure}', [FeeStructureController::class, 'update'])
                    ->whereNumber('structure')->can('fees.manage')->name('update');
            });

            Route::post('charges/{charge}/discount', [FeeAdjustmentController::class, 'discount'])
                ->whereNumber('charge')->can('fees.adjust')->name('charges.discount');
            Route::post('charges/{charge}/waive', [FeeAdjustmentController::class, 'waive'])
                ->whereNumber('charge')->can('fees.adjust')->name('charges.waive');
            Route::post('charges/{charge}/unwaive', [FeeAdjustmentController::class, 'unwaive'])
                ->whereNumber('charge')->can('fees.adjust')->name('charges.unwaive');

            Route::post('payments/{payment}/void', [FeePaymentController::class, 'void'])
                ->whereNumber('payment')->can('fees.adjust')->name('payments.void');

            Route::prefix('students/{student}')->name('students.')->whereNumber('student')->group(function () {
                Route::get('/', [FeeStatementController::class, 'show'])
                    ->can('fees.view')->name('show');
                Route::get('charges/create', [FeeChargeController::class, 'create'])
                    ->can('fees.manage')->name('charges.create');
                Route::post('charges', [FeeChargeController::class, 'store'])
                    ->can('fees.manage')->name('charges.store');
                Route::get('payments/create', [FeePaymentController::class, 'create'])
                    ->can('fees.record-payment')->name('payments.create');
                Route::post('payments', [FeePaymentController::class, 'store'])
                    ->can('fees.record-payment')->name('payments.store');
            });
        });

        /*
        | Learning Materials (see docs/learning-materials.md). Two gates:
        |   module:learning-materials  — is the feature on for this school?
        |   ->can('material.view')  — staff read access.
        |   ->can('material.upload')  — create/delete; a Teacher
        |     holding this without `.manage` is further scoped to classes/
        |     subjects they teach by LearningMaterialAuthorizer, re-checked
        |     inside the controller (the route gate alone can't express it).
        | {material} is resolved by tenant-scoped `findOrFail`, so another
        | school's id 404s.
        */
        Route::middleware('module:learning-materials')->prefix('learning-materials')->name('learning-materials.')->group(function () {
            Route::get('/', [LearningMaterialController::class, 'index'])
                ->can('material.view')->name('index');
            Route::get('create', [LearningMaterialController::class, 'create'])
                ->can('material.upload')->name('create');
            Route::post('/', [LearningMaterialController::class, 'store'])
                ->can('material.upload')->name('store');
            Route::get('{material}/download', [LearningMaterialController::class, 'download'])
                ->whereNumber('material')->can('material.view')->name('download');
            Route::delete('{material}', [LearningMaterialController::class, 'destroy'])
                ->whereNumber('material')->can('material.upload')->name('destroy');
        });

        /*
        | CBT / Online Examinations (see docs/cbt.md). Two gates:
        |   module:cbt  — is the feature on for this school?
        |   ->can('cbt.view')  — staff read access.
        |   ->can('cbt.author')  — create/edit-while-draft/attach questions/
        |     schedule/close; a Teacher holding this without `cbt.manage` is
        |     further scoped to classes/subjects they teach by CbtAuthorizer,
        |     re-checked inside the controller (the route gate alone can't
        |     express it).
        | {question}/{examination}/{examinationQuestion} are resolved by
        | tenant-scoped `findOrFail`, so another school's id 404s.
        */
        Route::middleware('module:cbt')->prefix('cbt')->name('cbt.')->group(function () {
            Route::prefix('questions')->name('questions.')->group(function () {
                Route::get('/', [QuestionController::class, 'index'])
                    ->can('cbt.view')->name('index');
                Route::get('create', [QuestionController::class, 'create'])
                    ->can('cbt.author')->name('create');
                Route::post('/', [QuestionController::class, 'store'])
                    ->can('cbt.author')->name('store');
                Route::get('{question}/preview', [QuestionController::class, 'preview'])
                    ->whereNumber('question')->can('cbt.view')->name('preview');
                Route::get('{question}/edit', [QuestionController::class, 'edit'])
                    ->whereNumber('question')->can('cbt.author')->name('edit');
                Route::patch('{question}', [QuestionController::class, 'update'])
                    ->whereNumber('question')->can('cbt.author')->name('update');
                Route::post('{question}/activate', [QuestionController::class, 'activate'])
                    ->whereNumber('question')->can('cbt.author')->name('activate');
                Route::post('{question}/deactivate', [QuestionController::class, 'deactivate'])
                    ->whereNumber('question')->can('cbt.author')->name('deactivate');
                Route::post('{question}/archive', [QuestionController::class, 'archive'])
                    ->whereNumber('question')->can('cbt.author')->name('archive');
            });

            Route::prefix('examinations')->name('examinations.')->group(function () {
                Route::get('/', [ExaminationController::class, 'index'])
                    ->can('cbt.view')->name('index');
                Route::get('create', [ExaminationController::class, 'create'])
                    ->can('cbt.author')->name('create');
                Route::post('/', [ExaminationController::class, 'store'])
                    ->can('cbt.author')->name('store');
                Route::get('{examination}', [ExaminationController::class, 'show'])
                    ->whereNumber('examination')->can('cbt.view')->name('show');
                Route::get('{examination}/edit', [ExaminationController::class, 'edit'])
                    ->whereNumber('examination')->can('cbt.author')->name('edit');
                Route::patch('{examination}', [ExaminationController::class, 'update'])
                    ->whereNumber('examination')->can('cbt.author')->name('update');
                Route::get('{examination}/preview', [ExaminationController::class, 'preview'])
                    ->whereNumber('examination')->can('cbt.view')->name('preview');
                Route::post('{examination}/schedule', [ExaminationController::class, 'schedule'])
                    ->whereNumber('examination')->can('cbt.author')->name('schedule');
                Route::post('{examination}/close', [ExaminationController::class, 'close'])
                    ->whereNumber('examination')->can('cbt.author')->name('close');

                Route::get('{examination}/questions/create', [ExaminationQuestionController::class, 'create'])
                    ->whereNumber('examination')->can('cbt.author')->name('questions.create');
                Route::post('{examination}/questions', [ExaminationQuestionController::class, 'store'])
                    ->whereNumber('examination')->can('cbt.author')->name('questions.store');
                Route::delete('{examination}/questions/{examinationQuestion}', [ExaminationQuestionController::class, 'destroy'])
                    ->whereNumber('examination')->whereNumber('examinationQuestion')->can('cbt.author')->name('questions.destroy');

                Route::get('{examination}/attempts', [ExaminationAttemptController::class, 'index'])
                    ->whereNumber('examination')->can('cbt.view')->name('attempts.index');
            });
        });

        /*
        | Communication Hub, Announcements & Notifications (see
        | docs/communication.md). module:notifications gates all of it.
        |   ->can('communication.view' | '.create' | '.manage' | '.resolve' |
        |     '.escalate')  — threads/messages, staff-only in this milestone.
        |   ->can('announcement.view' | '.manage')  — announcements (a viewer
        |     without .manage only ever sees published announcements targeted
        |     at their own role — see Announcement::scopeVisibleToRole()).
        |   /notifications needs no extra permission: it only ever shows the
        |     signed-in user's own notifications, tenant-scoped for free.
        | Tenant-owned ids ({thread}, {announcement}, {notification}) are
        | resolved by tenant-scoped `findOrFail`, so another school's id 404s.
        */
        Route::middleware('module:notifications')->group(function () {
            Route::prefix('communication')->name('communication.')->group(function () {
                Route::get('threads', [CommunicationThreadController::class, 'index'])
                    ->can('communication.view')->name('threads.index');
                Route::get('threads/create', [CommunicationThreadController::class, 'create'])
                    ->can('communication.create')->name('threads.create');
                Route::post('threads', [CommunicationThreadController::class, 'store'])
                    ->can('communication.create')->name('threads.store');
                Route::get('threads/{thread}', [CommunicationThreadController::class, 'show'])
                    ->whereNumber('thread')->can('communication.view')->name('threads.show');
                Route::patch('threads/{thread}', [CommunicationThreadController::class, 'update'])
                    ->whereNumber('thread')->can('communication.manage')->name('threads.update');
                Route::post('threads/{thread}/messages', [CommunicationMessageController::class, 'store'])
                    ->whereNumber('thread')->can('communication.create')->name('threads.messages.store');
                Route::post('threads/{thread}/resolve', [CommunicationStatusController::class, 'resolve'])
                    ->whereNumber('thread')->can('communication.resolve')->name('threads.resolve');
                Route::post('threads/{thread}/escalate', [CommunicationStatusController::class, 'escalate'])
                    ->whereNumber('thread')->can('communication.escalate')->name('threads.escalate');
                Route::post('threads/{thread}/reopen', [CommunicationStatusController::class, 'reopen'])
                    ->whereNumber('thread')->can('communication.resolve')->name('threads.reopen');
            });

            Route::prefix('announcements')->name('announcements.')->group(function () {
                Route::get('/', [AnnouncementController::class, 'index'])
                    ->can('announcement.view')->name('index');
                Route::get('create', [AnnouncementController::class, 'create'])
                    ->can('announcement.manage')->name('create');
                Route::post('/', [AnnouncementController::class, 'store'])
                    ->can('announcement.manage')->name('store');
                Route::get('{announcement}', [AnnouncementController::class, 'show'])
                    ->whereNumber('announcement')->can('announcement.view')->name('show');
                Route::get('{announcement}/edit', [AnnouncementController::class, 'edit'])
                    ->whereNumber('announcement')->can('announcement.manage')->name('edit');
                Route::patch('{announcement}', [AnnouncementController::class, 'update'])
                    ->whereNumber('announcement')->can('announcement.manage')->name('update');
                Route::post('{announcement}/publish', [AnnouncementController::class, 'publish'])
                    ->whereNumber('announcement')->can('announcement.manage')->name('publish');
                Route::post('{announcement}/unpublish', [AnnouncementController::class, 'unpublish'])
                    ->whereNumber('announcement')->can('announcement.manage')->name('unpublish');
            });

            Route::prefix('notifications')->name('notifications.')->group(function () {
                Route::get('/', [NotificationController::class, 'index'])->name('index');
                Route::post('{notification}/read', [NotificationController::class, 'read'])
                    ->whereNumber('notification')->name('read');
                Route::post('read-all', [NotificationController::class, 'readAll'])->name('read-all');
            });
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

            // Fee statement (M19, docs/fees.md) — read-only, nested so both
            // module gates apply. Online payment (M20, docs/paystack.md) is
            // the only write action this portal ever performs, and even
            // that never creates a charge or allocation directly — it only
            // ever starts a Paystack transaction; the verified callback is
            // what eventually records the real payment.
            Route::middleware('module:fees')->group(function () {
                Route::get('children/{student}/fees', [ParentFeeController::class, 'show'])
                    ->whereNumber('student')->can('portal.parent')->name('fees.show');

                Route::get('children/{student}/fees/pay', [ParentOnlinePaymentController::class, 'create'])
                    ->whereNumber('student')->can('portal.parent')->name('fees.pay.create');
                Route::post('children/{student}/fees/pay', [ParentOnlinePaymentController::class, 'store'])
                    ->whereNumber('student')->can('portal.parent')->name('fees.pay.store');
                Route::get('children/{student}/fees/pay/callback', [ParentOnlinePaymentController::class, 'callback'])
                    ->whereNumber('student')->can('portal.parent')->name('fees.pay.callback');
            });

            // Announcements & notifications (M18, docs/communication.md) —
            // AnnouncementController/NotificationController shared with staff
            // and the Student Portal; nested here so both module gates apply.
            Route::middleware('module:notifications')->group(function () {
                Route::get('announcements', [AnnouncementController::class, 'index'])
                    ->can('portal.parent')->name('announcements.index');
                Route::get('announcements/{announcement}', [AnnouncementController::class, 'show'])
                    ->whereNumber('announcement')->can('portal.parent')->name('announcements.show');

                Route::get('notifications', [NotificationController::class, 'index'])
                    ->can('portal.parent')->name('notifications.index');
                Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])
                    ->whereNumber('notification')->can('portal.parent')->name('notifications.read');
                Route::post('notifications/read-all', [NotificationController::class, 'readAll'])
                    ->can('portal.parent')->name('notifications.read-all');
            });
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

            // Fee statement (M19, docs/fees.md) — read-only, nested so both
            // module gates apply. Online payment (M20, docs/paystack.md) is
            // the only write action this portal ever performs, and even
            // that never creates a charge or allocation directly.
            Route::middleware('module:fees')->group(function () {
                Route::get('fees', [StudentFeeController::class, 'show'])
                    ->can('portal.student')->name('fees.show');

                Route::get('fees/pay', [StudentOnlinePaymentController::class, 'create'])
                    ->can('portal.student')->name('fees.pay.create');
                Route::post('fees/pay', [StudentOnlinePaymentController::class, 'store'])
                    ->can('portal.student')->name('fees.pay.store');
                Route::get('fees/pay/callback', [StudentOnlinePaymentController::class, 'callback'])
                    ->can('portal.student')->name('fees.pay.callback');
            });

            // Learning materials (M22, docs/learning-materials.md) —
            // read-only, scoped to the student's own current class. Not
            // middleware-gated by module:learning-materials (like
            // .assignments/.timetable above, unlike .fees below) — the
            // controller checks the module itself and degrades to an empty
            // state, so the portal's own nav never 404s.
            Route::get('learning-materials', [StudentLearningMaterialController::class, 'index'])
                ->can('portal.student')->name('learning-materials.index');
            Route::get('learning-materials/{material}/download', [StudentLearningMaterialController::class, 'download'])
                ->whereNumber('material')->can('portal.student')->name('learning-materials.download');

            // CBT / online examinations (M23, docs/cbt.md) — the listing
            // is not middleware-gated by module:cbt, same reasoning as
            // learning materials above; every deeper, state-mutating
            // action (start/take/answer/submit/result) is hard-gated so an
            // exam can never actually be sat while the module is off.
            Route::get('cbt', [StudentExaminationController::class, 'index'])
                ->can('cbt.take')->name('cbt.index');

            Route::middleware('module:cbt')->prefix('cbt')->name('cbt.')->group(function () {
                Route::get('{examination}', [StudentExaminationController::class, 'show'])
                    ->whereNumber('examination')->can('cbt.take')->name('show');
                Route::post('{examination}/start', [StudentExamAttemptController::class, 'start'])
                    ->whereNumber('examination')->can('cbt.take')->name('start');
                Route::get('{examination}/take', [StudentExamAttemptController::class, 'take'])
                    ->whereNumber('examination')->can('cbt.take')->name('take');
                Route::post('{examination}/answer', [StudentExamAttemptController::class, 'answer'])
                    ->whereNumber('examination')->can('cbt.take')->name('answer');
                Route::post('{examination}/submit', [StudentExamAttemptController::class, 'submit'])
                    ->whereNumber('examination')->can('cbt.take')->name('submit');
                Route::get('{examination}/result', [StudentExamAttemptController::class, 'result'])
                    ->whereNumber('examination')->can('cbt.take')->name('result');
            });

            // Announcements & notifications (M18, docs/communication.md) —
            // AnnouncementController/NotificationController shared with staff
            // and the Parent Portal; nested here so both module gates apply.
            Route::middleware('module:notifications')->group(function () {
                Route::get('announcements', [AnnouncementController::class, 'index'])
                    ->can('portal.student')->name('announcements.index');
                Route::get('announcements/{announcement}', [AnnouncementController::class, 'show'])
                    ->whereNumber('announcement')->can('portal.student')->name('announcements.show');

                Route::get('notifications', [NotificationController::class, 'index'])
                    ->can('portal.student')->name('notifications.index');
                Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])
                    ->whereNumber('notification')->can('portal.student')->name('notifications.read');
                Route::post('notifications/read-all', [NotificationController::class, 'readAll'])
                    ->can('portal.student')->name('notifications.read-all');
            });
        });
    });
});

require __DIR__.'/auth.php';
