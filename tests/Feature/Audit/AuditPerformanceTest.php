<?php

namespace Tests\Feature\Audit;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\School;
use Illuminate\Support\Facades\DB;

/**
 * N+1 regression guards for the Audit Log listing (M26 spec — "no N+1").
 * Asserts the query count stays flat as the number of entries grows, not
 * just that the page loads.
 */
class AuditPerformanceTest extends AuditTestCase
{
    private function countQueriesFor(callable $request): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $request();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        return $count;
    }

    private function logFor(School $school): AuditLog
    {
        return AuditLog::query()->create([
            'school_id' => $school->id,
            'actor_id' => null,
            'event' => 'test.event',
            'summary' => 'A performance test entry.',
            'created_at' => now(),
        ]);
    }

    public function test_index_query_count_does_not_grow_with_more_entries(): void
    {
        $school = School::factory()->create();
        $this->logFor($school);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $baseline = $this->countQueriesFor(fn () => $this->get('/administration/audit-log')->assertOk());

        for ($i = 0; $i < 10; $i++) {
            $this->logFor($school);
        }

        $afterMore = $this->countQueriesFor(fn () => $this->get('/administration/audit-log')->assertOk());

        $this->assertLessThanOrEqual($baseline + 2, $afterMore, 'the audit log index must not issue more queries as more entries are added');
    }

    public function test_filtered_query_count_also_stays_flat(): void
    {
        $school = School::factory()->create();
        $this->logFor($school);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $url = '/administration/audit-log?event=test.event';
        $baseline = $this->countQueriesFor(fn () => $this->get($url)->assertOk());

        for ($i = 0; $i < 10; $i++) {
            $this->logFor($school);
        }

        $afterMore = $this->countQueriesFor(fn () => $this->get($url)->assertOk());

        $this->assertLessThanOrEqual($baseline + 2, $afterMore, 'filtered audit log queries must not scale with row count either');
    }
}
