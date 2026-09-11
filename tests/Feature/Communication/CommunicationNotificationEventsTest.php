<?php

namespace Tests\Feature\Communication;

use App\Enums\Role;

/**
 * The event-driven notification foundation (M18 spec, section 4): a message
 * notifies the thread's assignee (never the sender of their own message), and
 * escalating a thread notifies everyone who holds `communication.manage`.
 */
class CommunicationNotificationEventsTest extends CommunicationTestCase
{
    public function test_a_reply_notifies_the_assignee_but_not_the_sender(): void
    {
        $school = $this->newSchool();
        $assignee = $this->memberOf($school, Role::Principal);
        $thread = $this->threadIn($school, ['assigned_to' => $assignee->id]);
        $sender = $this->actingAsRole($school, Role::Teacher);

        $this->post(route('communication.threads.messages.store', $thread), ['body' => 'Update.'])
            ->assertRedirect();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $assignee->id,
            'type' => 'communication_message_received',
        ]);
        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $sender->id,
            'type' => 'communication_message_received',
        ]);
    }

    public function test_a_reply_from_the_assignee_themselves_notifies_nobody(): void
    {
        $school = $this->newSchool();
        $assignee = $this->actingAsRole($school, Role::Principal);
        $thread = $this->threadIn($school, ['assigned_to' => $assignee->id]);

        $this->post(route('communication.threads.messages.store', $thread), ['body' => 'Self note.'])
            ->assertRedirect();

        $this->assertDatabaseMissing('user_notifications', ['type' => 'communication_message_received']);
    }

    public function test_escalating_notifies_every_manage_permission_holder_not_others(): void
    {
        $school = $this->newSchool();
        $admin = $this->memberOf($school, Role::SchoolAdmin);
        $principal = $this->memberOf($school, Role::Principal);
        $bursar = $this->memberOf($school, Role::Bursar);
        $thread = $this->threadIn($school);
        $this->actingAsRole($school, Role::Teacher);

        $this->post(route('communication.threads.escalate', $thread))->assertRedirect();

        $this->assertDatabaseHas('user_notifications', ['user_id' => $admin->id, 'type' => 'communication_escalated']);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $principal->id, 'type' => 'communication_escalated']);
        $this->assertDatabaseMissing('user_notifications', ['user_id' => $bursar->id, 'type' => 'communication_escalated']);
    }
}
