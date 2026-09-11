<?php

namespace App\Services\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Announcement;
use App\Models\Notification;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The single seam every module uses to raise an in-app notification, without
 * knowing anything about delivery channels — see
 * {@see NotificationChannel} and `docs/communication.md`. Only
 * `NotificationChannel::InApp` is ever actually written in this milestone;
 * WhatsApp/SMS/email are represented in the enum only, with no provider
 * behind them.
 *
 * Recipients are resolved and inserted in bulk (one query, not one
 * `Notification::create()` per user) so fanning a published announcement out
 * to a whole school stays cheap regardless of audience size.
 */
class NotificationDispatcher
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function sendToUser(User $user, NotificationType $type, string $title, string $message, ?string $url = null, array $data = []): void
    {
        $this->sendToUsers(collect([$user]), $type, $title, $message, $url, $data);
    }

    /**
     * @param  iterable<User>  $users
     */
    public function sendToUsers(iterable $users, NotificationType $type, string $title, string $message, ?string $url = null, array $data = []): void
    {
        $userIds = collect($users)->pluck('id')->unique()->values();

        if ($userIds->isEmpty()) {
            return;
        }

        $schoolId = $this->tenant->idOrFail();
        $now = Carbon::now();

        $rows = $userIds->map(fn (int $userId) => [
            'school_id' => $schoolId,
            'user_id' => $userId,
            'type' => $type->value,
            'channel' => NotificationChannel::InApp->value,
            'title' => $title,
            'message' => $message,
            'url' => $url,
            'data' => $data === [] ? null : json_encode($data),
            'read_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        Notification::query()->insert($rows);
    }

    /**
     * Every member of the active school holding $permission — used to reach
     * the handlers of an escalated communication thread.
     *
     * @return Collection<int, User>
     */
    public function usersWithPermission(Permission $permission): Collection
    {
        return $this->usersInRoles(array_values(array_filter(
            Role::all(),
            fn (Role $role) => $role->grants($permission),
        )));
    }

    /**
     * Fan a published announcement out to its target audience. Each audience
     * bucket (staff / parents / students) is notified with a URL into the
     * surface it will actually read the announcement from — the staff
     * Communication Hub, the Parent Portal, or the Student Portal.
     */
    public function notifyAnnouncementAudience(Announcement $announcement): void
    {
        $preview = Str::limit(strip_tags($announcement->body), 140);

        $buckets = [
            'staff' => [Role::SchoolAdmin, Role::Principal, Role::Bursar, Role::Teacher, Role::Staff],
            'parent' => [Role::Parent],
            'student' => [Role::Student],
        ];

        foreach ($buckets as $surface => $roles) {
            $roles = array_values(array_filter($roles, fn (Role $role) => $announcement->audience->includesRole($role)));

            if ($roles === []) {
                continue;
            }

            $url = match ($surface) {
                'staff' => route('announcements.show', $announcement),
                'parent' => route('parent.announcements.show', $announcement),
                'student' => route('student.announcements.show', $announcement),
            };

            $this->sendToUsers(
                $this->usersInRoles($roles),
                NotificationType::AnnouncementPublished,
                $announcement->title,
                $preview,
                $url,
            );
        }
    }

    /**
     * @param  list<Role>  $roles
     * @return Collection<int, User>
     */
    private function usersInRoles(array $roles): Collection
    {
        if ($roles === []) {
            return collect();
        }

        $schoolId = $this->tenant->idOrFail();
        $values = array_map(fn (Role $role) => $role->value, $roles);

        return User::query()
            ->whereHas('schools', fn ($q) => $q->where('schools.id', $schoolId)->whereIn('school_user.role', $values))
            ->get();
    }
}
