<?php

namespace App\Http\Controllers;

use App\Http\Requests\Settings\UpdateSchoolBrandingRequest;
use App\Http\Requests\Settings\UpdateSchoolPaymentsRequest;
use App\Http\Requests\Settings\UpdateSchoolProfileRequest;
use App\Http\Requests\Settings\UpdateSchoolRegionalRequest;
use App\Models\SchoolSetting;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The current school's configuration, split into focused sections (profile,
 * branding, regional). Tenant-scoped: `SchoolSetting` is a `BelongsToSchool`
 * model, so the row is always resolved through the active tenant's school
 * relation and `school_id` is stamped from the context — never from input.
 * Another school's settings (or logo) are unreachable here.
 *
 * `school.settings.view` gates the read pages; `school.settings.update` gates
 * every write (Form Request `authorize()` + route `->can()`).
 */
class SchoolSettingsController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    // -- Profile ----------------------------------------------------------

    public function edit(): View
    {
        $this->authorize('school.settings.view');

        return view('settings.school.profile', [
            'school' => $this->tenant->schoolOrFail(),
            'settings' => $this->settings(),
        ]);
    }

    public function update(UpdateSchoolProfileRequest $request): RedirectResponse
    {
        $this->save($request->validated());

        return to_route('settings.school.edit')->with('status', __('School profile saved.'));
    }

    // -- Branding -------------------------------------------------------

    public function branding(): View
    {
        $this->authorize('school.settings.view');

        return view('settings.school.branding', [
            'settings' => $this->settings(),
        ]);
    }

    public function updateBranding(UpdateSchoolBrandingRequest $request): RedirectResponse
    {
        $settings = $this->settings();
        $settings->fill(['brand_color' => $request->validated()['brand_color'] ?? null])->save();

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store(
                'school-logos/'.$this->tenant->idOrFail(),
                SchoolSetting::LOGO_DISK,
            );

            $settings->putLogo($path);
        }

        $settings->markReviewed();

        return to_route('settings.school.branding.edit')->with('status', __('Branding saved.'));
    }

    public function destroyLogo(): RedirectResponse
    {
        $this->authorize('school.settings.update');

        $this->settings()->clearLogo();

        return to_route('settings.school.branding.edit')->with('status', __('Logo removed.'));
    }

    public function showLogo(): StreamedResponse|Response
    {
        $this->authorize('school.settings.view');

        $settings = $this->settings();

        abort_unless($settings->hasLogo(), 404);

        return Storage::disk(SchoolSetting::LOGO_DISK)
            ->response($settings->logo_path, 'logo', ['Cache-Control' => 'private, max-age=300']);
    }

    // -- Regional -----------------------------------------------------

    public function regional(): View
    {
        $this->authorize('school.settings.view');

        return view('settings.school.regional', [
            'settings' => $this->settings(),
        ]);
    }

    public function updateRegional(UpdateSchoolRegionalRequest $request): RedirectResponse
    {
        $this->save($request->validated());

        return to_route('settings.school.regional.edit')->with('status', __('Regional settings saved.'));
    }

    // -- Online payment (M20) -------------------------------------------

    public function payments(): View
    {
        $this->authorize('school.settings.view');

        return view('settings.school.payments', [
            'settings' => $this->settings(),
        ]);
    }

    public function updatePayments(UpdateSchoolPaymentsRequest $request): RedirectResponse
    {
        $settings = $this->settings();

        $settings->fill([
            'paystack_enabled' => $request->boolean('paystack_enabled'),
            'paystack_public_key' => $request->publicKey(),
            'paystack_test_mode' => $request->boolean('paystack_test_mode'),
        ]);

        // A blank secret field means "keep the existing key" — it is never
        // rendered back into the form, so there is nothing to "clear" here.
        if ($request->newSecretKey() !== null) {
            $settings->paystack_secret_key = $request->newSecretKey();
        }

        $settings->save();
        $settings->markReviewed();

        return to_route('settings.school.payments.edit')->with('status', __('Payment settings saved.'));
    }

    // -- helpers ----------------------------------------------------

    private function settings(): SchoolSetting
    {
        // hasOne::firstOrCreate() sets school_id from the parent; the
        // BelongsToSchool creating hook confirms it matches the active tenant.
        return $this->tenant->schoolOrFail()->settings()->firstOrCreate([]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function save(array $attributes): void
    {
        $settings = $this->settings();
        $settings->fill($attributes)->save();
        $settings->markReviewed();
    }
}
