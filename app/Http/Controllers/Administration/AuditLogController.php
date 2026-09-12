<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The staff-facing Audit Log (M26, `docs/audit.md`) — a read-only,
 * chronological view of `AuditLog` rows for the active school. Gated
 * `audit.view` alone; there is deliberately no edit/destroy route anywhere
 * in the app, which is what makes an audit record immutable from the UI.
 *
 * `AuditLog` is **not** `BelongsToSchool` (`school_id` is nullable — see
 * the migration), so every query here adds `where('school_id',
 * TenantContext::idOrFail())` explicitly rather than relying on a global
 * scope. This also means the account-level security events recorded with a
 * null `school_id` (login/logout/password reset/email verification — see
 * `docs/audit.md` §2) never appear in any school's audit log; that is a
 * deliberate scope boundary, not a bug.
 */
class AuditLogController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(Request $request): View
    {
        $this->authorize('audit.view');

        return view('administration.audit-log.index', [
            'logs' => $this->filteredQuery($request)->paginate(25)->withQueryString(),
            ...$this->filterOptions(),
            'filters' => $request->only(['q', 'event', 'actor', 'type', 'from', 'to']),
        ]);
    }

    public function show(int $auditLog): View
    {
        $this->authorize('audit.view');

        $log = AuditLog::query()
            ->where('school_id', $this->tenant->idOrFail())
            ->with('actor:id,name')
            ->findOrFail($auditLog);

        return view('administration.audit-log.show', ['log' => $log]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('audit.view');

        $filename = 'audit-log-'.now()->format('Y-m-d-His').'.csv';
        $columns = ['Date/time', 'Event', 'Actor', 'Affected record', 'Summary', 'IP address'];
        $query = $this->filteredQuery($request);

        return response()->streamDownload(function () use ($query, $columns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $columns);

            // chunk() keeps memory bounded to one page at a time while still
            // eager-loading each page's `actor` relation in bulk — never
            // cursor(), which would skip eager loading and N+1 per row.
            $query->chunk(200, function (Collection $chunk) use ($handle) {
                foreach ($chunk as $log) {
                    /** @var AuditLog $log */
                    fputcsv($handle, [
                        $log->created_at?->toDateTimeString(),
                        $log->event,
                        $log->actor_name,
                        $log->auditable_label,
                        $log->summary,
                        $log->ip_address,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return Builder<AuditLog>
     */
    private function filteredQuery(Request $request): Builder
    {
        $query = AuditLog::query()
            ->where('school_id', $this->tenant->idOrFail())
            ->with('actor:id,name')
            ->ordered();

        if ($request->filled('q')) {
            $query->search($request->string('q')->trim()->value());
        }
        if ($request->filled('event')) {
            $query->where('event', $request->string('event')->value());
        }
        if ($request->filled('actor')) {
            $query->where('actor_id', $request->integer('actor'));
        }
        if ($request->filled('type')) {
            $query->where('auditable_type', $request->string('type')->value());
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->date('to'));
        }

        return $query;
    }

    /**
     * Filter option lists, derived from what actually exists for this
     * school rather than a maintained static catalogue — the set of event
     * names and affected-record types naturally grows as new modules call
     * `AuditRecorder::record()`, with nothing here to keep in sync.
     *
     * @return array{events: Collection, types: Collection, actors: Collection}
     */
    private function filterOptions(): array
    {
        $schoolId = $this->tenant->idOrFail();

        $events = AuditLog::query()
            ->where('school_id', $schoolId)
            ->distinct()
            ->orderBy('event')
            ->pluck('event');

        $types = AuditLog::query()
            ->where('school_id', $schoolId)
            ->whereNotNull('auditable_type')
            ->distinct()
            ->orderBy('auditable_type')
            ->pluck('auditable_type')
            ->mapWithKeys(fn (string $type) => [$type => class_basename($type)]);

        $actors = $this->tenant->schoolOrFail()->users()
            ->orderBy('name')
            ->get(['users.id', 'users.name']);

        return compact('events', 'types', 'actors');
    }
}
