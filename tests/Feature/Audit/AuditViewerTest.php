<?php

namespace Tests\Feature\Audit;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\User;

/**
 * The Audit Log viewer (M26, `docs/audit.md`): search, filters (event,
 * user, date range), pagination and the detail view.
 */
class AuditViewerTest extends AuditTestCase
{
    private function logFor(School $school, array $overrides = []): AuditLog
    {
        return AuditLog::query()->create(array_merge([
            'school_id' => $school->id,
            'event' => 'test.event',
            'summary' => 'A test audit entry.',
            'created_at' => now(),
        ], $overrides));
    }

    public function test_search_filters_by_summary(): void
    {
        $school = School::factory()->create();
        $this->logFor($school, ['summary' => 'Findable entry about Ada Okafor.']);
        $this->logFor($school, ['summary' => 'Unrelated entry about someone else.']);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->get('/administration/audit-log?q=Ada+Okafor')
            ->assertOk()
            ->assertSee('Findable entry about Ada Okafor.')
            ->assertDontSee('Unrelated entry about someone else.');
    }

    public function test_filter_by_event(): void
    {
        $school = School::factory()->create();
        $this->logFor($school, ['event' => 'student.created', 'summary' => 'Created a student.']);
        $this->logFor($school, ['event' => 'teacher.created', 'summary' => 'Created a teacher.']);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->get('/administration/audit-log?event=student.created')
            ->assertOk()
            ->assertSee('Created a student.')
            ->assertDontSee('Created a teacher.');
    }

    public function test_filter_by_actor(): void
    {
        $school = School::factory()->create();
        $alice = $this->memberOf($school, Role::Teacher, ['name' => 'Alice Actor']);
        $bob = $this->memberOf($school, Role::Teacher, ['name' => 'Bob Actor']);
        $this->logFor($school, ['actor_id' => $alice->id, 'actor_name' => $alice->name, 'summary' => 'Alice did something.']);
        $this->logFor($school, ['actor_id' => $bob->id, 'actor_name' => $bob->name, 'summary' => 'Bob did something.']);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->get("/administration/audit-log?actor={$alice->id}")
            ->assertOk()
            ->assertSee('Alice did something.')
            ->assertDontSee('Bob did something.');
    }

    public function test_filter_by_date_range(): void
    {
        $school = School::factory()->create();
        $this->logFor($school, ['summary' => 'An old entry.', 'created_at' => now()->subDays(10)]);
        $this->logFor($school, ['summary' => 'A recent entry.', 'created_at' => now()]);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->get('/administration/audit-log?from='.now()->subDays(2)->toDateString())
            ->assertOk()
            ->assertSee('A recent entry.')
            ->assertDontSee('An old entry.');
    }

    public function test_filter_by_affected_type(): void
    {
        $school = School::factory()->create();
        $this->logFor($school, ['auditable_type' => 'App\\Models\\Student', 'summary' => 'A student event.']);
        $this->logFor($school, ['auditable_type' => 'App\\Models\\Teacher', 'summary' => 'A teacher event.']);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->get('/administration/audit-log?type='.urlencode('App\\Models\\Student'))
            ->assertOk()
            ->assertSee('A student event.')
            ->assertDontSee('A teacher event.');
    }

    public function test_index_paginates(): void
    {
        $school = School::factory()->create();
        for ($i = 0; $i < 30; $i++) {
            $this->logFor($school, ['summary' => "Entry number {$i}."]);
        }
        $this->actingAsRole($school, Role::SchoolAdmin);

        $response = $this->get('/administration/audit-log');
        $response->assertOk();
        $response->assertViewHas('logs', fn ($paginator) => $paginator->perPage() === 25 && $paginator->total() === 30);
    }

    public function test_detail_view_shows_before_after_changes(): void
    {
        $school = School::factory()->create();
        $log = $this->logFor($school, [
            'summary' => 'Detail view test entry.',
            'changes' => ['before' => ['status' => 'active'], 'after' => ['status' => 'archived']],
        ]);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $response = $this->get("/administration/audit-log/{$log->id}");
        $response->assertOk();
        $response->assertSee('Detail view test entry.');
        $response->assertSee('active');
        $response->assertSee('archived');
    }

    public function test_empty_state_is_shown_when_no_entries_match(): void
    {
        $school = School::factory()->create();
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->get('/administration/audit-log?q=nothing-will-match-this')
            ->assertOk()
            ->assertSee('No audit entries match');
    }
}
