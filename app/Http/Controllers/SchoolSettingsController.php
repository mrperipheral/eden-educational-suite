<?php

namespace App\Http\Controllers;

use App\Http\Requests\Settings\UpdateSchoolBrandingRequest;
use App\Http\Requests\Settings\UpdateSchoolPaymentsRequest;
use App\Http\Requests\Settings\UpdateSchoolProfileRequest;
use App\Http\Requests\Settings\UpdateSchoolRegionalRequest;
use App\Models\SchoolSetting;
use App\Services\Audit\AuditRecorder;
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
    public function __construct(private readonly TenantContext $tenant, private readonly AuditRecorder $audit) {}

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
        $this->save($request->validated(), 'settings.profile_updated', __('School profile updated.'));

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
        $before = $settings->getAttributes();
        $settings->fill([
            'brand_color' => $request->validated()['brand_color'] ?? null,
            'accent_color' => $request->validated()['accent_color'] ?? null,
            'motto' => $request->validated()['motto'] ?? null,
        ])->save();

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store(
                'school-logos/'.$this->tenant->idOrFail(),
                SchoolSetting::LOGO_DISK,
            );

            $settings->putLogo($path);
        }

        if ($request->hasFile('cover')) {
            $path = $request->file('cover')->store(
                'school-covers/'.$this->tenant->idOrFail(),
                SchoolSetting::COVER_DISK,
            );

            $settings->putCover($path);
        }

        $settings->markReviewed();

        $this->audit->record(
            event: 'settings.branding_updated',
            summary: __(':actor updated the school branding.', ['actor' => $request->user()->name]),
            auditable: $settings,
            auditableLabel: $this->tenant->schoolOrFail()->name,
            before: $before,
            after: $settings->getAttributes(),
        );

        return to_route('settings.school.branding.edit')->with('status', __('Branding saved.'));
    }

    public function destroyLogo(): RedirectResponse
    {
        $this->authorize('school.settings.update');

        $settings = $this->settings();
        $settings->clearLogo();

        $this->audit->record(
            event: 'settings.branding_updated',
            summary: __(':actor removed the school logo.', ['actor' => request()->user()->name]),
            auditable: $settings,
            auditableLabel: $this->tenant->schoolOrFail()->name,
        );

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

    public function destroyCover(): RedirectResponse
    {
        $this->authorize('school.settings.update');

        $settings = $this->settings();
        $settings->clearCover();

        $this->audit->record(
            event: 'settings.branding_updated',
            summary: __(':actor removed the school cover image.', ['actor' => request()->user()->name]),
            auditable: $settings,
            auditableLabel: $this->tenant->schoolOrFail()->name,
        );

        return to_route('settings.school.branding.edit')->with('status', __('Cover image removed.'));
    }

    public function showCover(): StreamedResponse|Response
    {
        $this->authorize('school.settings.view');

        $settings = $this->settings();

        abort_unless($settings->hasCover(), 404);

        return Storage::disk(SchoolSetting::COVER_DISK)
            ->response($settings->cover_image_path, 'cover', ['Cache-Control' => 'private, max-age=300']);
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
        $this->save($request->validated(), 'settings.regional_updated', __('School regional settings updated.'));

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
        $secretChanged = $request->newSecretKey() !== null;
        if ($secretChanged) {
            $settings->paystack_secret_key = $request->newSecretKey();
        }

        $settings->save();
        $settings->markReviewed();

        // The secret key itself is never included, changed or not — left
        // out of the payload entirely rather than relying solely on
        // AuditRecorder's blanket redaction. `key_was_rotated` (not
        // `..._secret_...`) is deliberately named to avoid tripping that
        // same redaction filter — it is a boolean flag, not a value that
        // needs masking, and redacting it would hide a true/false behind
        // "[redacted]" for no protective benefit.
        $this->audit->record(
            event: 'settings.payments_updated',
            summary: __(':actor updated the online payment settings.', ['actor' => $request->user()->name]),
            auditable: $settings,
            auditableLabel: $this->tenant->schoolOrFail()->name,
            after: [
                'paystack_enabled' => $settings->paystack_enabled,
                'paystack_test_mode' => $settings->paystack_test_mode,
                'paystack_key_was_rotated' => $secretChanged,
            ],
        );

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
    private function save(array $attributes, string $event, string $summary): void
    {
        $settings = $this->settings();
        $before = $settings->getAttributes();
        $settings->fill($attributes)->save();
        $settings->markReviewed();

        $this->audit->record(
            event: $event,
            summary: __(':actor :summary', ['actor' => request()->user()->name, 'summary' => lcfirst($summary)]),
            auditable: $settings,
            auditableLabel: $this->tenant->schoolOrFail()->name,
            before: $before,
            after: $settings->getAttributes(),
        );
    }
}
