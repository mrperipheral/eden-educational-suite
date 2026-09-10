<?php

namespace App\Models;

use App\Enums\GuardianRelationship;
use App\Support\Tenancy\Concerns\BelongsToSchool;
use App\Support\Tenancy\SchoolScope;
use Database\Factories\GuardianStudentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * One student ↔ guardian relationship. School-owned ({@see BelongsToSchool})
 * *and* it carries both `student_id` and `guardian_id`, so a query is tenant-safe
 * without a parent in the join.
 *
 * `student_id` is set from the parent relation on create and never changes;
 * `guardian_id` is a tenant-validated id from the request; `school_id` is stamped
 * from the tenant context. `relationship` is explicit; `is_primary` marks the
 * student's main contact — at most one per student ({@see self::makePrimary()}).
 * The `(student_id, guardian_id)` pair is unique — no duplicate links.
 */
class GuardianStudent extends Model
{
    /** @use HasFactory<GuardianStudentFactory> */
    use BelongsToSchool, HasFactory;

    protected $table = 'guardian_student';

    /** @var list<string> */
    protected $fillable = [
        'guardian_id',
        'relationship',
        'is_primary',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'relationship' => GuardianRelationship::class,
            'is_primary' => 'boolean',
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
     * Make this guardian the student's primary contact, demoting any other
     * primary link the student has. The queries are tenant-scoped by
     * {@see SchoolScope} and further limited to this student.
     */
    public function makePrimary(): void
    {
        DB::transaction(function () {
            static::query()
                ->where('student_id', $this->student_id)
                ->whereKeyNot($this->getKey())
                ->where('is_primary', true)
                ->update(['is_primary' => false]);

            $this->is_primary = true;
            $this->save();
        });
    }

    /**
     * @param  Builder<GuardianStudent>  $query
     */
    public function scopePrimary(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_primary'), true);
    }
}
