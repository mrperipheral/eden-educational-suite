<?php

namespace App\Support\Tenancy\Concerns;

use App\Models\School;
use App\Support\Tenancy\Exceptions\TenantMismatchException;
use App\Support\Tenancy\SchoolScope;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Apply to every school-owned model.
 *
 *   use App\Support\Tenancy\Concerns\BelongsToSchool;
 *
 *   class Announcement extends Model
 *   {
 *       use BelongsToSchool;
 *   }
 *
 * What it does:
 *
 *   1. Adds {@see SchoolScope} — reads/updates/deletes are constrained to the
 *      active school automatically.
 *   2. On `creating`, stamps `school_id` from the tenant context. A client that
 *      supplies a *different* `school_id` gets a {@see TenantMismatchException};
 *      a matching or absent value is fine.
 *   3. On `updating`, forbids any change to `school_id` — ownership is immutable.
 *
 * Migrations for these models MUST start their lookup indexes with the school
 * column, e.g. `$table->index(['school_id', 'created_at'])` — see
 * docs/database-design.md.
 *
 * Never add the school column to `$fillable`. This trait is the only thing that
 * should ever write it.
 */
trait BelongsToSchool
{
    public static function bootBelongsToSchool(): void
    {
        static::addGlobalScope(new SchoolScope);

        static::creating(function (Model $model): void {
            /** @var Model&self $model */
            $column = $model->getSchoolIdColumn();
            $contextId = app(TenantContext::class)->idOrFail();
            $given = $model->getAttribute($column);

            if ($given !== null && (int) $given !== $contextId) {
                throw new TenantMismatchException(
                    class_basename($model)." cannot be created for school #{$given}: "
                    ."the active tenant is school #{$contextId}."
                );
            }

            $model->setAttribute($column, $contextId);
        });

        static::updating(function (Model $model): void {
            /** @var Model&self $model */
            $column = $model->getSchoolIdColumn();

            if ($model->isDirty($column)) {
                throw new TenantMismatchException(
                    class_basename($model).' ownership is immutable: '."`{$column}` cannot be changed."
                );
            }
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, $this->getSchoolIdColumn());
    }

    /**
     * Name of the foreign-key column. Override with a `SCHOOL_ID` class constant
     * if a model ever needs a non-standard name.
     */
    public function getSchoolIdColumn(): string
    {
        return defined(static::class.'::SCHOOL_ID') ? static::SCHOOL_ID : 'school_id';
    }

    public function getQualifiedSchoolIdColumn(): string
    {
        return $this->qualifyColumn($this->getSchoolIdColumn());
    }

    /**
     * Explicitly query a specific school, bypassing the ambient context. For
     * platform-admin / cross-tenant tooling only — normal code relies on the
     * global scope.
     */
    public function scopeForSchool(Builder $query, School|int $school): Builder
    {
        return $query
            ->withoutGlobalScope(SchoolScope::class)
            ->where($this->getQualifiedSchoolIdColumn(), $school instanceof School ? $school->getKey() : $school);
    }
}
