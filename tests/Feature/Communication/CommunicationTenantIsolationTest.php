<?php

namespace Tests\Feature\Communication;

use App\Enums\Role;
use App\Models\Announcement;
use App\Models\Notification;

/**
 * Cross-school isolation for the Communication Hub (M18 spec, section 7):
 * a school user can never view, reply to, or otherwise touch another
 * school's thread, and cannot link a thread to another school's student or
 * guardian.
 */
class CommunicationTenantIsolationTest extends CommunicationTestCase
{
    public function test_another_schools_thread_404s(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $thread = $this->threadIn($schoolB, ['subject' => 'Belongs to school B']);

        $this->actingAsRole($schoolA, Role::SchoolAdmin);

        $this->get(route('communication.threads.show', $thread))->assertNotFound();
        $this->post(route('communication.threads.messages.store', $thread), ['body' => 'hi'])->assertNotFound();
        $this->post(route('communication.threads.resolve', $thread))->assertNotFound();
        $this->post(route('communication.threads.escalate', $thread))->assertNotFound();
        $this->patch(route('communication.threads.update', $thread), ['subject' => 'x', 'category' => 'general'])->assertNotFound();
    }

    public function test_cannot_open_a_thread_against_another_schools_student(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();
        $foreignStudent = $this->studentIn($schoolB);

        $this->actingAsRole($schoolA, Role::Teacher);

        $this->post(route('communication.threads.store'), [
            'subject' => 'Cross-school attempt',
            'category' => 'general',
            'student_id' => $foreignStudent->id,
            'body' => 'Should be rejected.',
        ])->assertSessionHasErrors('student_id');
    }

    public function test_another_schools_announcement_404s(): void
    {
        $schoolA = $this->newSchool();
        $schoolB = $this->newSchool();

        $this->enterSchool($schoolB);
        $announcement = new Announcement(['title' => 'B only', 'body' => 'x', 'audience' => 'everyone']);
        $announcement->created_by = $this->memberOf($schoolB)->id;
        $announcement->save();
        $announcement->publish();
        $this->app->forgetScopedInstances();

        $this->actingAsRole($schoolA, Role::SchoolAdmin);
        $this->get(route('announcements.show', $announcement))->assertNotFound();
    }

    public function test_notifications_are_never_visible_across_users_or_schools(): void
    {
        $school = $this->newSchool();
        $owner = $this->actingAsRole($school, Role::SchoolAdmin);

        $this->enterSchool($school);
        $notification = Notification::factory()->create(['user_id' => $owner->id]);
        $this->app->forgetScopedInstances();

        $otherSchool = $this->newSchool();
        $this->actingAsRole($otherSchool, Role::SchoolAdmin);
        $this->post(route('notifications.read', $notification))->assertNotFound();

        $this->actingAsRole($school, Role::Teacher);
        $this->post(route('notifications.read', $notification))->assertNotFound();
    }
}
