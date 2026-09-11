<?php

namespace App\Models;

use App\Enums\CommunicationCategory;
use App\Enums\CommunicationStatus;
use App\Events\CommunicationThreadEscalated;
use App\Support\Tenancy\Concerns\BelongsToSchool;
use Database\Factories\CommunicationThreadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A Communication Hub thread — one piece of school communication, optionally
 * about a specific {@see Student} and/or {@see Guardian}. School-owned
 * ({@see BelongsToSchool}). Staff-facing in this milestone: every
 * participant is a `User` (`created_by` / `assigned_to`) — see
 * `docs/communication.md`.
 *
 * `status` is **not** mass-assignable — it changes only through
 * {@see self::resolve()} / {@see self::escalate()} / {@see self::reopen()}.
 * Never hard-deleted.
 */
class CommunicationThread extends Model
{
    /** @use HasFactory<CommunicationThreadFactory> */
    use BelongsToSchool, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'student_id',
        'guardian_id',
        'assigned_to',
        'category',
        'subject',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'category' => CommunicationCategory::General->value,
        'status' => CommunicationStatus::Open->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => CommunicationCategory::class,
            'status' => CommunicationStatus::class,
            'resolved_at' => 'datetime',
            'escalated_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Guardian, $this>
     */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return HasMany<CommunicationMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(CommunicationMessage::class);
    }

    public function resolve(): void
    {
        $this->status = CommunicationStatus::Resolved;
        $this->resolved_at = Carbon::now();
        $this->escalated_at = null;
        $this->save();
    }

    public function escalate(): void
    {
        $this->status = CommunicationStatus::Escalated;
        $this->escalated_at = Carbon::now();
        $this->save();

        event(new CommunicationThreadEscalated($this));
    }

    public function reopen(): void
    {
        $this->status = CommunicationStatus::Open;
        $this->resolved_at = null;
        $this->escalated_at = null;
        $this->save();
    }

    /**
     * @param  Builder<CommunicationThread>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc($this->qualifyColumn('last_message_at'))->orderByDesc($this->qualifyColumn('id'));
    }

    /**
     * @param  Builder<CommunicationThread>  $query
     */
    public function scopeStatus(Builder $query, ?CommunicationStatus $status): void
    {
        if ($status !== null) {
            $query->where($this->qualifyColumn('status'), $status);
        }
    }

    /**
     * @param  Builder<CommunicationThread>  $query
     */
    public function scopeCategory(Builder $query, ?CommunicationCategory $category): void
    {
        if ($category !== null) {
            $query->where($this->qualifyColumn('category'), $category);
        }
    }
}
