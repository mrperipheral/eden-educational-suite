<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An in-app notification for one recipient. School-owned
 * ({@see BelongsToSchool}), its own `user_notifications` table — deliberately
 * **not** Laravel's conventional polymorphic `notifications` table, which has
 * no `school_id` and would bypass tenant isolation. See
 * `docs/communication.md`.
 *
 * Rows are created only by
 * {@see NotificationDispatcher}; nothing here is
 * ever written from request input.
 */
class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use BelongsToSchool, HasFactory;

    protected $table = 'user_notifications';

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'type',
        'channel',
        'title',
        'message',
        'url',
        'data',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'channel' => NotificationChannel::InApp->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'channel' => NotificationChannel::class,
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function markRead(): void
    {
        if ($this->read_at === null) {
            $this->read_at = Carbon::now();
            $this->save();
        }
    }

    /**
     * @param  Builder<Notification>  $query
     */
    public function scopeForUser(Builder $query, User $user): void
    {
        $query->where($this->qualifyColumn('user_id'), $user->getKey());
    }

    /**
     * @param  Builder<Notification>  $query
     */
    public function scopeUnread(Builder $query): void
    {
        $query->whereNull($this->qualifyColumn('read_at'));
    }

    /**
     * @param  Builder<Notification>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc($this->qualifyColumn('created_at'))->orderByDesc($this->qualifyColumn('id'));
    }
}
