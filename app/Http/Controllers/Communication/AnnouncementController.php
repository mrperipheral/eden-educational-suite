<?php

namespace App\Http\Controllers\Communication;

use App\Enums\AnnouncementAudience;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\AnnouncementRequest;
use App\Models\Announcement;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Announcements. `index` / `show` are mounted at three route names —
 * `announcements.*` (staff), `parent.announcements.*`, `student.announcements.*`
 * — one controller, no duplication, matching the M16/M17 shared-renderer
 * pattern. A viewer holding `announcement.manage` sees every announcement
 * (draft + published, any audience); everyone else sees only published
 * announcements whose audience targets their own role
 * ({@see Announcement::scopeVisibleToRole()}) — that query is the actual
 * "visible only to intended recipients" guarantee, not the route gate.
 *
 * `create` / `store` / `edit` / `update` / `publish` / `unpublish` are
 * `announcement.manage` only (Principal / School Admin). `{announcement}` is
 * resolved by tenant-scoped `findOrFail`, so another school's id 404s.
 */
class AnnouncementController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $manage = $user->hasPermission(Permission::AnnouncementManage);
        $role = $user->roleIn($this->tenant->schoolOrFail());

        $announcements = Announcement::query()
            ->with('createdBy:id,name')
            ->when(! $manage, fn ($q) => $q->visibleToRole($role))
            ->ordered()
            ->paginate(15)
            ->withQueryString();

        return view('communication.announcements.index', [
            'announcements' => $announcements,
            'canManage' => $manage,
        ]);
    }

    public function create(): View
    {
        $this->authorize('announcement.manage');

        return view('communication.announcements.create', [
            'audiences' => AnnouncementAudience::all(),
        ]);
    }

    public function store(AnnouncementRequest $request): RedirectResponse
    {
        $announcement = new Announcement($request->payload());
        $announcement->created_by = $request->user()->getKey();
        $announcement->save();

        return to_route('announcements.show', $announcement)->with('status', __('Announcement saved as draft.'));
    }

    public function show(Request $request, int $announcement): View
    {
        $user = $request->user();
        $manage = $user->hasPermission(Permission::AnnouncementManage);
        $role = $user->roleIn($this->tenant->schoolOrFail());

        $announcement = Announcement::query()
            ->with('createdBy:id,name')
            ->when(! $manage, fn ($q) => $q->visibleToRole($role))
            ->findOrFail($announcement);

        return view('communication.announcements.show', [
            'announcement' => $announcement,
            'canManage' => $manage,
        ]);
    }

    public function edit(int $announcement): View
    {
        $this->authorize('announcement.manage');

        return view('communication.announcements.edit', [
            'announcement' => Announcement::query()->findOrFail($announcement),
            'audiences' => AnnouncementAudience::all(),
        ]);
    }

    public function update(AnnouncementRequest $request, int $announcement): RedirectResponse
    {
        $announcement = Announcement::query()->findOrFail($announcement);
        $announcement->update($request->payload());

        return to_route('announcements.show', $announcement)->with('status', __('Announcement updated.'));
    }

    public function publish(int $announcement): RedirectResponse
    {
        $this->authorize('announcement.manage');

        $announcement = Announcement::query()->findOrFail($announcement);
        $announcement->publish();

        return to_route('announcements.show', $announcement)->with('status', __('Announcement published.'));
    }

    public function unpublish(int $announcement): RedirectResponse
    {
        $this->authorize('announcement.manage');

        $announcement = Announcement::query()->findOrFail($announcement);
        $announcement->unpublish();

        return to_route('announcements.show', $announcement)->with('status', __('Announcement unpublished.'));
    }
}
