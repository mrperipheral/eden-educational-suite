<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\SchoolContextController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
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
        Route::patch('members/{user}', [MemberController::class, 'updateRole'])->name('members.update-role');
        Route::delete('members/{user}', [MemberController::class, 'destroy'])->name('members.destroy');
    });
});

require __DIR__.'/auth.php';
