<?php

namespace Tests\Feature\Communication;

use App\Enums\CommunicationStatus;
use App\Enums\Role;
use App\Http\Middleware\EnforceTenant;
use App\Models\CommunicationThread;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\Route;

class CommunicationThreadTest extends CommunicationTestCase
{
    public function test_staff_with_create_permission_can_open_a_thread_with_its_first_message(): void
    {
        $school = $this->newSchool();
        $this->actingAsRole($school, Role::Teacher);

        $response = $this->post(route('communication.threads.store'), [
            'subject' => 'Late pickup',
            'category' => 'general',
            'body' => 'Parent asked about the late pickup policy.',
        ]);

        $thread = CommunicationThread::first();
        $response->assertRedirect(route('communication.threads.show', $thread));

        $this->assertSame('Late pickup', $thread->subject);
        $this->assertSame(CommunicationStatus::Open, $thread->status);
        $this->assertNotNull($thread->created_by);
        $this->assertNotNull($thread->last_message_at);
        $this->assertSame(1, $thread->messages()->count());

        $message = $thread->messages()->first();
        $this->assertNotNull($message->sender_id);
        $this->assertSame('Parent asked about the late pickup policy.', $message->body);
    }

    public function test_bursar_and_staff_can_view_and_create_but_not_resolve_or_escalate(): void
    {
        $school = $this->newSchool();
        $thread = $this->threadIn($school, ['subject' => 'Fee query']);

        foreach ([Role::Bursar, Role::Staff] as $role) {
            $user = $this->actingAsRole($school, $role);

            $this->get(route('communication.threads.index'))->assertOk();
            $this->get(route('communication.threads.show', $thread))->assertOk();
            $this->post(route('communication.threads.resolve', $thread))->assertForbidden();
            $this->post(route('communication.threads.escalate', $thread))->assertForbidden();
        }
    }

    public function test_teacher_can_resolve_and_escalate_but_not_manage(): void
    {
        $school = $this->newSchool();
        $thread = $this->threadIn($school, ['subject' => 'Behavior note']);
        $this->actingAsRole($school, Role::Teacher);

        $this->post(route('communication.threads.resolve', $thread))->assertRedirect();
        $this->assertSame(CommunicationStatus::Resolved, $thread->fresh()->status);

        $this->post(route('communication.threads.reopen', $thread))->assertRedirect();
        $this->assertSame(CommunicationStatus::Open, $thread->fresh()->status);

        $this->post(route('communication.threads.escalate', $thread))->assertRedirect();
        $this->assertSame(CommunicationStatus::Escalated, $thread->fresh()->status);

        $this->patch(route('communication.threads.update', $thread), [
            'subject' => 'Renamed', 'category' => 'general',
        ])->assertForbidden();
    }

    public function test_principal_can_manage_a_thread(): void
    {
        $school = $this->newSchool();
        $thread = $this->threadIn($school, ['subject' => 'Original subject']);
        $this->actingAsRole($school, Role::Principal);

        $this->patch(route('communication.threads.update', $thread), [
            'subject' => 'Updated subject', 'category' => 'academic',
        ])->assertRedirect(route('communication.threads.show', $thread));

        $this->assertSame('Updated subject', $thread->fresh()->subject);
    }

    public function test_parent_and_student_cannot_reach_the_communication_hub(): void
    {
        $school = $this->newSchool();
        $thread = $this->threadIn($school);

        foreach ([Role::Parent, Role::Student] as $role) {
            $this->actingAsRole($school, $role);
            $this->get(route('communication.threads.index'))->assertForbidden();
            $this->get(route('communication.threads.show', $thread))->assertForbidden();
        }
    }

    public function test_reply_updates_last_message_at_and_is_attributed_to_the_replier(): void
    {
        $school = $this->newSchool();
        $thread = $this->threadIn($school, ['subject' => 'Uniform question']);
        $user = $this->actingAsRole($school, Role::Staff);

        $this->post(route('communication.threads.messages.store', $thread), [
            'body' => 'Following up on this.',
        ])->assertRedirect(route('communication.threads.show', $thread));

        $thread->refresh();
        $this->assertSame(1, $thread->messages()->count());
        $message = $thread->messages()->first();
        $this->assertSame($user->id, $message->sender_id);
        $this->assertNotNull($thread->last_message_at);
    }

    public function test_thread_is_never_hard_deletable_there_is_no_destroy_route(): void
    {
        $this->assertFalse(Route::has('communication.threads.destroy'));
    }

    public function test_module_off_404s_every_communication_route(): void
    {
        $school = $this->newSchool();
        $this->disableNotifications($school);
        $thread = $this->threadIn($school);
        $this->actingAsRole($school, Role::SchoolAdmin);

        $this->get(route('communication.threads.index'))->assertNotFound();
        $this->get(route('communication.threads.show', $thread))->assertNotFound();
        $this->post(route('communication.threads.store'), [])->assertNotFound();
    }

    public function test_role_less_member_is_forbidden(): void
    {
        $school = $this->newSchool();
        $this->memberOfNoRole($school);

        $this->get(route('communication.threads.index'))->assertForbidden();
    }

    private function memberOfNoRole(School $school): void
    {
        $user = User::factory()->create();
        $user->joinSchool($school, null);
        $this->actingAs($user);
        $this->withSession([EnforceTenant::SESSION_KEY => $school->getKey()]);
    }
}
