<?php

namespace Tests\Feature\Communication;

use App\Enums\Role;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;

class NotificationTest extends CommunicationTestCase
{
    public function test_index_shows_only_the_signed_in_users_own_notifications(): void
    {
        $school = $this->newSchool();
        $mine = $this->actingAsRole($school, Role::SchoolAdmin);

        $this->enterSchool($school);
        Notification::factory()->create(['user_id' => $mine->id, 'title' => 'Mine']);
        $other = $this->memberOf($school);
        Notification::factory()->create(['user_id' => $other->id, 'title' => 'Not mine']);
        $this->app->forgetScopedInstances();

        $this->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Mine')
            ->assertDontSee('Not mine');
    }

    public function test_mark_read_sets_read_at_and_redirects_to_the_notifications_url(): void
    {
        $school = $this->newSchool();
        $user = $this->actingAsRole($school, Role::SchoolAdmin);

        $this->enterSchool($school);
        $notification = Notification::factory()->create(['user_id' => $user->id, 'url' => '/dashboard']);
        $this->app->forgetScopedInstances();

        $this->post(route('notifications.read', $notification))->assertRedirect('/dashboard');
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_all_read_only_touches_the_signed_in_users_rows(): void
    {
        $school = $this->newSchool();
        $user = $this->actingAsRole($school, Role::SchoolAdmin);

        $this->enterSchool($school);
        $mine1 = Notification::factory()->create(['user_id' => $user->id]);
        $mine2 = Notification::factory()->create(['user_id' => $user->id]);
        $other = $this->memberOf($school);
        $notMine = Notification::factory()->create(['user_id' => $other->id]);
        $this->app->forgetScopedInstances();

        $this->post(route('notifications.read-all'))->assertRedirect();

        $this->assertNotNull($mine1->fresh()->read_at);
        $this->assertNotNull($mine2->fresh()->read_at);
        $this->assertNull($notMine->fresh()->read_at);
    }

    public function test_unread_count_reflects_only_unread_rows(): void
    {
        $school = $this->newSchool();
        $user = $this->actingAsRole($school, Role::SchoolAdmin);

        $this->enterSchool($school);
        Notification::factory()->count(2)->create(['user_id' => $user->id]);
        Notification::factory()->read()->create(['user_id' => $user->id]);
        $this->app->forgetScopedInstances();

        $response = $this->get(route('notifications.index'))->assertOk();
        $response->assertViewHas('unreadCount', 2);
    }

    public function test_parent_and_student_reuse_the_same_notification_center(): void
    {
        $school = $this->newSchool();

        $parent = $this->actingAsRole($school, Role::Parent);
        $this->enterSchool($school);
        Notification::factory()->create(['user_id' => $parent->id, 'title' => 'Parent notice']);
        $this->app->forgetScopedInstances();
        $this->get(route('parent.notifications.index'))->assertOk()->assertSee('Parent notice');

        $student = $this->actingAsRole($school, Role::Student);
        $this->enterSchool($school);
        Notification::factory()->create(['user_id' => $student->id, 'title' => 'Student notice']);
        $this->app->forgetScopedInstances();
        $this->get(route('student.notifications.index'))->assertOk()->assertSee('Student notice');
    }

    public function test_notification_index_does_not_n_plus_one_for_a_long_history(): void
    {
        $school = $this->newSchool();
        $user = $this->actingAsRole($school, Role::SchoolAdmin);

        $this->enterSchool($school);
        Notification::factory()->count(18)->create(['user_id' => $user->id]);
        $this->app->forgetScopedInstances();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('notifications.index'))->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(10, $queries, "the notification index ran {$queries} queries for 18 notifications");
    }

    public function test_module_off_404s_notification_routes(): void
    {
        $school = $this->newSchool();
        $this->disableNotifications($school);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->get(route('notifications.index'))->assertNotFound();
    }
}
