<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The notification centre — shared by staff, the Parent Portal and the
 * Student Portal alike (mounted at `notifications.*`, `parent.notifications.*`
 * and `student.notifications.*`, one controller). Every query is scoped to
 * `auth()->user()` and the active tenant ({@see Notification} is
 * `BelongsToSchool`), so no permission beyond `module:notifications` is
 * needed — a member only ever sees their own notifications, the same way
 * `/settings/profile` needs no extra gate. See `docs/communication.md`.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $notifications = Notification::query()
            ->forUser($user)
            ->ordered()
            ->paginate(20)
            ->withQueryString();

        return view('notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => Notification::query()->forUser($user)->unread()->count(),
        ]);
    }

    public function read(Request $request, int $notification): RedirectResponse
    {
        $notification = Notification::query()->forUser($request->user())->findOrFail($notification);
        $notification->markRead();

        return $notification->url !== null
            ? redirect($notification->url)
            : back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        Notification::query()
            ->forUser($request->user())
            ->unread()
            ->update(['read_at' => now()]);

        return back()->with('status', __('All notifications marked read.'));
    }
}
