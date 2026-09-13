<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Reports\PlatformReport;
use Illuminate\View\View;

/**
 * Platform-level cross-school reporting (M27 §13) — school overview,
 * module adoption, platform usage, all on one page. Gated exactly like
 * `Platform\SchoolController` (`->authorize('viewAny', School::class)` —
 * `SchoolPolicy`, platform-admin only), **not** a new permission: this is
 * the same "may this user administer the platform at all" question the
 * existing Schools screen already answers, not a separate reporting
 * capability. Not tenant-scoped by design — see `App\Reports\
 * PlatformReport` for how cross-school aggregation stays deliberate and
 * bounded rather than a blanket bypass. See `docs/reporting.md` §13.
 */
class ReportController extends Controller
{
    public function __construct(private readonly PlatformReport $report) {}

    public function index(): View
    {
        $this->authorize('viewAny', School::class);

        return view('platform.reports.index', [
            'schoolOverview' => $this->report->schoolOverview(),
            'moduleAdoption' => $this->report->moduleAdoption(),
            'platformUsage' => $this->report->platformUsage(),
        ]);
    }
}
