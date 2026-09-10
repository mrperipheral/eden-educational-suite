<?php

namespace Tests\Feature\Tenancy;

use App\Support\Tenancy\Exceptions\MissingTenantContextException;
use App\Support\Tenancy\Exceptions\TenantMismatchException;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\Fixtures\Tenancy\TenantThing;
use Tests\TestCase;

class BelongsToSchoolTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    public function test_create_stamps_school_id_from_the_active_context(): void
    {
        $school = $this->enterSchool($this->newSchool());

        $thing = TenantThing::create(['label' => 'Timetable']);

        $this->assertSame($school->id, $thing->school_id);
        $this->assertDatabaseHas('tenant_things', ['label' => 'Timetable', 'school_id' => $school->id]);
    }

    public function test_create_ignores_a_matching_school_id_but_rejects_a_foreign_one(): void
    {
        $school = $this->enterSchool($this->newSchool());
        $other = $this->newSchool();

        // Matching value: fine.
        $ok = new TenantThing(['label' => 'ok']);
        $ok->school_id = $school->id;
        $ok->save();
        $this->assertSame($school->id, $ok->fresh()->school_id);

        // Spoofed value: rejected.
        $this->expectException(TenantMismatchException::class);
        $spoof = new TenantThing(['label' => 'spoof']);
        $spoof->school_id = $other->id;
        $spoof->save();
    }

    public function test_create_without_a_context_throws(): void
    {
        $this->expectException(MissingTenantContextException::class);

        TenantThing::create(['label' => 'orphan']);
    }

    public function test_reads_are_scoped_to_the_active_school(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();

        $this->enterSchool($a);
        TenantThing::create(['label' => 'A-1']);
        TenantThing::create(['label' => 'A-2']);

        $this->enterSchool($b);
        TenantThing::create(['label' => 'B-1']);

        $this->assertSame(1, TenantThing::count());
        $this->assertEqualsCanonicalizing(['B-1'], TenantThing::pluck('label')->all());

        $this->enterSchool($a);
        $this->assertSame(2, TenantThing::count());
        $this->assertEqualsCanonicalizing(['A-1', 'A-2'], TenantThing::pluck('label')->all());
    }

    public function test_cannot_find_another_schools_record(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();

        $this->enterSchool($a);
        $thingA = TenantThing::create(['label' => 'A']);

        $this->enterSchool($b);

        $this->assertNull(TenantThing::find($thingA->id));
        $this->assertSame(0, TenantThing::whereKey($thingA->id)->count());
    }

    public function test_cannot_update_another_schools_record_via_query(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();

        $this->enterSchool($a);
        $thingA = TenantThing::create(['label' => 'original']);

        $this->enterSchool($b);
        $affected = TenantThing::whereKey($thingA->id)->update(['label' => 'hacked']);

        $this->assertSame(0, $affected);
        $this->assertSame('original', $thingA->fresh()->label);
    }

    public function test_cannot_delete_another_schools_record_via_query(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();

        $this->enterSchool($a);
        $thingA = TenantThing::create(['label' => 'keep me']);

        $this->enterSchool($b);
        $deleted = TenantThing::whereKey($thingA->id)->delete();

        $this->assertSame(0, $deleted);
        $this->assertDatabaseHas('tenant_things', ['id' => $thingA->id]);
    }

    public function test_school_id_is_immutable_on_update(): void
    {
        $a = $this->enterSchool($this->newSchool());
        $b = $this->newSchool();

        $thing = TenantThing::create(['label' => 'x']);

        $thing->school_id = $b->id;

        $this->expectException(TenantMismatchException::class);
        $thing->save();
    }

    public function test_run_without_scope_sees_every_school(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();

        $this->enterSchool($a);
        TenantThing::create(['label' => 'A']);
        $this->enterSchool($b);
        TenantThing::create(['label' => 'B']);

        $all = app(TenantContext::class)->runWithoutScope(fn () => TenantThing::pluck('label')->all());

        $this->assertEqualsCanonicalizing(['A', 'B'], $all);
    }

    public function test_for_school_scope_targets_a_specific_school_explicitly(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();

        $this->enterSchool($a);
        TenantThing::create(['label' => 'A']);
        $this->enterSchool($b);
        TenantThing::create(['label' => 'B']);

        // Context is still B, but an explicit forSchool() reaches A only.
        $this->assertEqualsCanonicalizing(
            ['A'],
            TenantThing::query()->forSchool($a)->pluck('label')->all()
        );
    }

    public function test_without_school_scope_macro_removes_the_constraint(): void
    {
        $a = $this->newSchool();
        $b = $this->newSchool();

        $this->enterSchool($a);
        TenantThing::create(['label' => 'A']);
        $this->enterSchool($b);
        TenantThing::create(['label' => 'B']);

        $all = app(TenantContext::class)->runWithoutScope(
            fn () => TenantThing::query()->withoutSchoolScope()->pluck('label')->all()
        );

        $this->assertEqualsCanonicalizing(['A', 'B'], $all);
    }

    public function test_the_school_relationship_resolves(): void
    {
        $school = $this->enterSchool($this->newSchool());
        $thing = TenantThing::create(['label' => 'x']);

        $this->assertTrue($thing->school->is($school));
    }
}
