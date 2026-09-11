<?php

namespace App\Models;

use App\Enums\AnnouncementAudience;
use App\Enums\AnnouncementStatus;
use App\Enums\Role;
use App\Events\AnnouncementPublished;
use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A school-scoped announcement. School-owned ({@see BelongsToSchool}). See
 * `docs/communication.md`.
 *
 * `status` / `published_at` are **not** mass-assignable — they change only
 * through {@see self::publish()} / {@see self::unpublish()}. A draft is
 * visible to managers (`announcement.manage`) only; a published announcement
 * is visible to whichever role its `audience` targets.
 */
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'title',
        'body',
        'audience',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'audience' => AnnouncementAudience::Everyone->value,
        'status' => AnnouncementStatus::Draft->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'audience' => AnnouncementAudience::class,
            'status' => AnnouncementStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPublished(): bool
    {
        return $this->status === AnnouncementStatus::Published;
    }

    public function publish(): void
    {
        $this->status = AnnouncementStatus::Published;
        $this->published_at = Carbon::now();
        $this->save();

        event(new AnnouncementPublished($this));
    }

    public function unpublish(): void
    {
        $this->status = AnnouncementStatus::Draft;
        $this->published_at = null;
        $this->save();
    }

    /**
     * @param  Builder<Announcement>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc($this->qualifyColumn('published_at'))->orderByDesc($this->qualifyColumn('id'));
    }

    /**
     * Published announcements whose audience targets $role — the visibility
     * rule for anyone without `announcement.manage` (staff viewers and both
     * portals alike).
     *
     * @param  Builder<Announcement>  $query
     */
    public function scopeVisibleToRole(Builder $query, Role $role): void
    {
        $query->where($this->qualifyColumn('status'), AnnouncementStatus::Published)
            ->where(function (Builder $q) use ($role) {
                foreach (AnnouncementAudience::all() as $audience) {
                    if ($audience->includesRole($role)) {
                        $q->orWhere($this->qualifyColumn('audience'), $audience);
                    }
                }
            });
    }
}
