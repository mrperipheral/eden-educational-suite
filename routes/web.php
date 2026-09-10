<?php

use App\Http\Controllers\AcademicSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\Platform\SchoolController as PlatformSchoolController;
use App\Http\Controllers\SchoolContextController;
use App\Http\Controllers\SchoolModuleController;
use App\Http\Controllers\SchoolSettingsController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
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

        Route::get('settings/academic-sessions', [AcademicSessionController::class, 'index'])
            ->can('school.settings.view')
            ->name('academic-sessions.index');
        Route::post('settings/academic-sessions', [AcademicSessionController::class, 'store'])
            ->can('school.settings.update')
            ->name('academic-sessions.store');
        Route::patch('settings/academic-sessions/{session}', [AcademicSessionController::class, 'update'])
            ->whereNumber('session')
            ->can('school.settings.update')
            ->name('academic-sessions.update');
    });
});

require __DIR__.'/auth.php';
