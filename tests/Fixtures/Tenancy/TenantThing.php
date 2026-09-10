<?php

namespace Tests\Fixtures\Tenancy;

use App\Support\Tenancy\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

/**
 * Minimal school-owned model used only by the tenancy test-suite. It exercises
 * the real BelongsToSchool trait / SchoolScope without shipping a domain table
 * before its milestone.
 *
 * Its table is created by the InteractsWithTenancy test trait.
 */
class TenantThing extends Model
{
    use BelongsToSchool;

    protected $table = 'tenant_things';

    /** `school_id` is deliberately NOT fillable — the trait owns it. */
    protected $fillable = ['label'];
}
