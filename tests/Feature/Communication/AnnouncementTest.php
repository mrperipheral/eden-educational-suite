<?php

namespace Tests\Feature\Communication;

use App\Enums\Role;
use App\Models\Announcement;

class AnnouncementTest extends CommunicationTestCase
{
    public function test_principal_can_create_edit_and_publish_an_announcement(): void
    {
        $school = $this->newSchool();
        $this->actingAsRole($school, Role::Principal);

        $this->post(route('announcements.store'), [
            'title' => 'Sports day',
            'body' => 'Sports day is next Friday.',
            'audience' => 'everyone',
        ])->assertRedirect();

        $announcement = Announcement::first();
        $this->assertTrue($announcement->status->value === 'draft');
        $this->assertNotNull($announcement->created_by);

        $this->patch(route('announcements.update', $announcement), [
            'title' => 'Sports day (updated)',
            'body' => $announcement->body,
            'audience' => 'everyone',
        ])->assertRedirect();
        $this->assertSame('Sports day (updated)', $announcement->fresh()->title);

        $this->post(route('announcements.publish', $announcement))->assertRedirect();
        $announcement->refresh();
        $this->assertTrue($announcement->isPublished());
        $this->assertNotNull($announcement->published_at);

        $this->post(route('announcements.unpublish', $announcement))->assertRedirect();
        $this->assertFalse($announcement->fresh()->isPublished());
    }

    public function test_teacher_cannot_manage_announcements(): void
    {
        $school = $this->newSchool();
        $this->actingAsRole($school, Role::Teacher);

        $this->get(route('announcements.create'))->assertForbidden();
        $this->post(route('announcements.store'), ['title' => 'x', 'body' => 'y', 'audience' => 'everyone'])->assertForbidden();
    }

    public function test_draft_announcements_are_invisible_to_non_managers(): void
    {
        $school = $this->newSchool();
        $draft = $this->announcementIn($school, ['audience' => 'everyone']);

        $this->actingAsRole($school, Role::Teacher);
        $this->get(route('announcements.show', $draft))->assertNotFound();
        $this->get(route('announcements.index'))->assertOk()->assertDontSee($draft->title);
    }

    public function test_published_announcement_is_visible_only_to_its_target_audience(): void
    {
        $school = $this->newSchool();
        $teacherOnly = $this->announcementIn($school, ['audience' => 'teachers', 'title' => 'For teachers'], published: true);

        $this->actingAsRole($school, Role::Teacher);
        $this->get(route('announcements.show', $teacherOnly))->assertOk();

        $this->actingAsRole($school, Role::Bursar);
        $this->get(route('announcements.show', $teacherOnly))->assertNotFound();
    }

    public function test_parent_and_student_only_see_published_announcements_targeted_at_them(): void
    {
        $school = $this->newSchool();
        $parentAnnouncement = $this->announcementIn($school, ['audience' => 'parents', 'title' => 'For parents'], published: true);
        $studentAnnouncement = $this->announcementIn($school, ['audience' => 'students', 'title' => 'For students'], published: true);

        $this->actingAsRole($school, Role::Parent);
        $this->get(route('parent.announcements.show', $parentAnnouncement))->assertOk();
        $this->get(route('parent.announcements.show', $studentAnnouncement))->assertNotFound();

        $this->actingAsRole($school, Role::Student);
        $this->get(route('student.announcements.show', $studentAnnouncement))->assertOk();
        $this->get(route('student.announcements.show', $parentAnnouncement))->assertNotFound();
    }

    public function test_publishing_an_announcement_notifies_its_audience(): void
    {
        $school = $this->newSchool();
        $teacher = $this->memberOf($school, Role::Teacher);

        $this->announcementIn($school, ['audience' => 'teachers'], published: true);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $teacher->id,
            'type' => 'announcement_published',
        ]);
    }

    public function test_module_off_404s_announcement_routes(): void
    {
        $school = $this->newSchool();
        $this->disableNotifications($school);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->get(route('announcements.index'))->assertNotFound();
    }

    private function announcementIn($school, array $attributes = [], bool $published = false): Announcement
    {
        $this->enterSchool($school);

        $announcement = new Announcement(array_merge(['title' => 'Untitled', 'body' => 'Body'], $attributes));
        $announcement->created_by = $this->memberOf($school)->id;
        $announcement->save();

        if ($published) {
            $announcement->publish();
        }

        $this->app->forgetScopedInstances();

        return $announcement;
    }
}
