<?php

use App\Http\Controllers\Academic\ArmController;
use App\Http\Controllers\Academic\LevelController;
use App\Http\Controllers\Academic\PeriodController;
use App\Http\Controllers\Academic\SessionController as AcademicSessionController;
use App\Http\Controllers\Academic\SubjectController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\Platform\SchoolController as PlatformSchoolController;
use App\Http\Controllers\SchoolContextController;
use App\Http\Controllers\SchoolModuleController;
use App\Http\Controllers\SchoolSettingsController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Student\EnrollmentController;
use App\Http\Controllers\Student\StudentController;
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
        });
    });
});

require __DIR__.'/auth.php';
