<?php

namespace Tests\Feature\Tenancy;

use App\Http\Middleware\EnforceTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\InteractsWithTenancy;
use Tests\Fixtures\Tenancy\TenantThing;
use Tests\TestCase;

/**
 * End-to-end: exercises the REAL middleware stack (auth → verified → active →
 * tenant) against a tenant-owned model, proving School A can never read, create,
 * update or delete School B's rows through an ordinary request.
 */
class CrossSchoolIsolationTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Route::middleware(['web', 'auth', 'verified', 'active', 'tenant'])->group(function () {
            Route::get('_test/things', fn () => TenantThing::pluck('label'));
            Route::post('_test/things', fn () => TenantThing::create(['label' => request('label')]));
            Route::put('_test/things/{id}', fn ($id) => (string) TenantThing::whereKey($id)->update(['label' => 'updated']));
            Route::delete('_test/things/{id}', fn ($id) => (string) TenantThing::whereKey($id)->delete());
            Route::get('_test/things/{id}', fn ($id) => TenantThing::findOrFail($id));
        });
    }

    public function test_users_only_ever_see_their_own_schools_rows(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();

        $userA = $this->actingAsMemberOf($schoolA);
        $this->post('/_test/things', ['label' => 'A-secret'])->assertSuccessful();

        $this->app->forgetScopedInstances();
        $userB = $this->actingAsMemberOf($schoolB);
        $this->post('/_test/things', ['label' => 'B-secret'])->assertSuccessful();

        $this->get('/_test/things')->assertOk()->assertSee('B-secret')->assertDontSee('A-secret');

        $this->app->forgetScopedInstances();
        $this->actingAs($userA)->withSession([EnforceTenant::SESSION_KEY => $schoolA->id]);
        $this->get('/_test/things')->assertOk()->assertSee('A-secret')->assertDontSee('B-secret');
    }

    public function test_a_user_cannot_read_update_or_delete_another_schools_record(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();

        // Seed one row in school A.
        $this->enterSchool($schoolA);
        $rowA = TenantThing::create(['label' => 'A-only']);
        $this->app->forgetScopedInstances();

        // Act as a school B user for the rest.
        $this->actingAsMemberOf($schoolB);

        $this->get("/_test/things/{$rowA->id}")->assertNotFound();
        $this->put("/_test/things/{$rowA->id}")->assertOk()->assertSee('0', false);      // 0 rows affected
        $this->delete("/_test/things/{$rowA->id}")->assertOk()->assertSee('0', false);   // 0 rows deleted

        $this->assertDatabaseHas('tenant_things', ['id' => $rowA->id, 'label' => 'A-only']);
    }

    public function test_created_rows_are_always_stamped_with_the_requesters_school(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();

        $this->actingAsMemberOf($schoolA);
        $this->post('/_test/things', ['label' => 'thing'])->assertSuccessful();

        $this->assertDatabaseHas('tenant_things', ['label' => 'thing', 'school_id' => $schoolA->id]);
        $this->assertDatabaseMissing('tenant_things', ['label' => 'thing', 'school_id' => $schoolB->id]);
    }

    public function test_a_user_cannot_switch_into_a_school_they_do_not_belong_to(): void
    {
        $mine = $this->newSchool();
        $notMine = $this->newSchool();
        $user = $this->memberOf($mine);

        $this->actingAs($user)
            ->post('/school', ['school' => $notMine->id])
            ->assertForbidden();

        $this->assertNotSame($notMine->id, session(EnforceTenant::SESSION_KEY));
    }
}
