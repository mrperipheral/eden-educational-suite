<?php

namespace App\Support\Tenancy;

use App\Support\Tenancy\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope applied by {@see BelongsToSchool}.
 *
 * Every read, update and delete against a tenant-owned model is constrained to
 * the active school unless the tenant context is explicitly bypassed
 * ({@see TenantContext::runWithoutScope()}) or the scope is removed for one
 * query (`Model::query()->withoutSchoolScope()` / `->forSchool($school)`).
 *
 * If no context is active and no bypass is in effect, this throws rather than
 * running an unscoped query.
 */
class SchoolScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenant = app(TenantContext::class);

        if ($tenant->isBypassed()) {
            return;
        }

        /** @var BelongsToSchool $model */
        $builder->where($model->getQualifiedSchoolIdColumn(), $tenant->idOrFail());
    }

    public function extend(Builder $builder): void
    {
        $builder->macro('withoutSchoolScope', function (Builder $builder) {
            return $builder->withoutGlobalScope(static::class);
        });
    }
}
