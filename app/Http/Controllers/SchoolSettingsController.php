<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSchoolSettingsRequest;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The current school's basic settings. Tenant-scoped: `SchoolSetting` is a
 * `BelongsToSchool` model, so the settings row is resolved through the school
 * relation of the active tenant and `school_id` is stamped from the context —
 * never from request input. Another school's settings are unreachable here.
 */
class SchoolSettingsController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function edit(): View
    {
        $this->authorize('school.settings.view');

        return view('settings.school', [
            'settings' => $this->settings(),
        ]);
    }

    public function update(UpdateSchoolSettingsRequest $request): RedirectResponse
    {
        $settings = $this->settings();
        $settings->fill($request->validated())->save();
        $settings->markReviewed();

        return to_route('settings.school.edit')
            ->with('status', __('School settings saved.'));
    }

    private function settings()
    {
        // hasOne::firstOrCreate() sets school_id from the parent; the
        // BelongsToSchool creating hook confirms it matches the active tenant.
        return $this->tenant->schoolOrFail()->settings()->firstOrCreate([]);
    }
}
