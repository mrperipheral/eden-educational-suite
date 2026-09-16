<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\SchoolSetting;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A school's own public entry page — `/schools/{school}` (bound by slug, see
 * `School::getRouteKeyName()`) — reachable by anyone, no login required.
 * Deliberately minimal: name, logo, motto, a few profile fields, and Login /
 * Register links. This is **not** a website builder — there is no editable
 * content, no arbitrary HTML/CSS, nothing beyond what `SchoolSetting` already
 * stores for the branded portal itself (see `docs/school-settings.md`).
 *
 * Never establishes a tenant context (no `tenant` middleware, and
 * `School::resolveActiveBySlug()` explicitly reads outside tenant scope) —
 * this page exists entirely outside the authenticated application.
 */
class SchoolHomeController extends Controller
{
    public function show(School $school): View
    {
        abort_unless($school->isActive(), 404);

        return view('schools.home', [
            'school' => School::resolveActiveBySlug($school->slug),
        ]);
    }

    /**
     * The school's logo, served publicly (no `school.settings.view`
     * permission gate — unlike the authenticated branding route this
     * mirrors) because this page itself is public. Still school-scoped by
     * slug and still never serves anything but the active school's own
     * logo file.
     */
    public function logo(School $school): StreamedResponse|Response
    {
        abort_unless($school->isActive(), 404);

        $settings = School::resolveActiveBySlug($school->slug)?->settings;

        abort_unless($settings?->hasLogo(), 404);

        return Storage::disk(SchoolSetting::LOGO_DISK)
            ->response($settings->logo_path, 'logo', ['Cache-Control' => 'public, max-age=300']);
    }
}
