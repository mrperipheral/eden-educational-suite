<?php

namespace Tests\Concerns;

use App\Enums\Role;
use App\Http\Middleware\EnforceTenant;
use App\Models\School;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\Tenancy\TenantThing;

/**
 * Helpers for tenancy tests. Also provisions the `tenant_things` fixture table
 * (see {@see TenantThing}).
 */
trait InteractsWithTenancy
{
    protected function setUpInteractsWithTenancy(): void
    {
        if (! Schema::hasTable('tenant_things')) {
            Schema::create('tenant_things', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id');
                $table->string('label');
                $table->timestamps();

                $table->index(['school_id', 'id']);
            });
        }
    }

    protected function newSchool(array $attributes = []): School
    {
        return School::factory()->create($attributes);
    }

    /**
     * Set the active tenant context directly (for non-HTTP model tests).
     */
    protected function enterSchool(School $school): School
    {
        app(TenantContext::class)->set($school);

        return $school;
    }

    protected function memberOf(School $school, ?Role $role = null, array $userAttributes = []): User
    {
        $user = User::factory()->create($userAttributes);
        $user->joinSchool($school, $role);

        return $user;
    }

    /**
     * Authenticate as a member of $school (optionally with a role) and pin that
     * school as the session's active context — the state a normal request
     * reaches after EnforceTenant.
     */
    protected function actingAsMemberOf(School $school, ?Role $role = null, array $userAttributes = []): User
    {
        $user = $this->memberOf($school, $role, $userAttributes);
        $this->actingAs($user);
        $this->withSession([EnforceTenant::SESSION_KEY => $school->getKey()]);

        return $user;
    }

    /**
     * Read the stored role for (user, school) straight from the pivot table,
     * bypassing any in-memory memo on the User model.
     */
    protected function storedRole(User $user, School $school): ?Role
    {
        $value = \DB::table('school_user')
            ->where('user_id', $user->getKey())
            ->where('school_id', $school->getKey())
            ->value('role');

        return $value !== null ? Role::from($value) : null;
    }

    protected function actingAsPlatformAdmin(?School $activeSchool = null): User
    {
        $user = User::factory()->platformAdmin()->create();
        $this->actingAs($user);

        if ($activeSchool !== null) {
            $this->withSession([EnforceTenant::SESSION_KEY => $activeSchool->getKey()]);
        }

        return $user;
    }
}
