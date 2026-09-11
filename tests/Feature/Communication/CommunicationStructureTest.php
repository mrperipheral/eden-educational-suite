<?php

namespace Tests\Feature\Communication;

use App\Enums\Module;
use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Domain boundaries for M18: the Notifications module is reused (not
 * re-declared) for the whole Communication Hub / Announcements / Notifications
 * surface, the new permissions slot into the existing role tiers, and nothing
 * here is hard-deletable.
 */
class CommunicationStructureTest extends CommunicationTestCase
{
    public function test_notifications_module_is_available_and_depends_on_nothing(): void
    {
        $this->assertTrue(Module::Notifications->isAvailable());
        $this->assertSame([], Module::Notifications->dependencies());
        $this->assertTrue(Module::Notifications->enabledByDefault());
    }

    public function test_school_admin_holds_every_new_permission_automatically(): void
    {
        foreach ([
            Permission::CommunicationView, Permission::CommunicationCreate, Permission::CommunicationManage,
            Permission::CommunicationResolve, Permission::CommunicationEscalate,
            Permission::AnnouncementView, Permission::AnnouncementManage,
        ] as $permission) {
            $this->assertTrue(Role::SchoolAdmin->grants($permission));
        }
    }

    public function test_role_tiers_hold_the_expected_communication_permissions(): void
    {
        $this->assertTrue(Role::Principal->grants(Permission::CommunicationManage));
        $this->assertTrue(Role::Principal->grants(Permission::AnnouncementManage));

        $this->assertFalse(Role::Teacher->grants(Permission::CommunicationManage));
        $this->assertTrue(Role::Teacher->grants(Permission::CommunicationResolve));
        $this->assertTrue(Role::Teacher->grants(Permission::CommunicationEscalate));

        $this->assertFalse(Role::Staff->grants(Permission::CommunicationResolve));
        $this->assertTrue(Role::Staff->grants(Permission::CommunicationCreate));

        $this->assertFalse(Role::Parent->grants(Permission::CommunicationView));
        $this->assertFalse(Role::Student->grants(Permission::CommunicationView));
        $this->assertFalse(Role::Parent->grants(Permission::AnnouncementManage));
    }

    public function test_new_tables_lead_their_lookup_indexes_with_school_id(): void
    {
        foreach (['communication_threads', 'communication_messages', 'announcements', 'user_notifications'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'school_id'), "{$table} must carry school_id");
        }
    }

    public function test_nothing_in_the_communication_hub_is_hard_deletable(): void
    {
        foreach ([
            'communication.threads.destroy', 'communication.threads.messages.destroy',
            'announcements.destroy', 'notifications.destroy',
        ] as $route) {
            $this->assertFalse(Route::has($route), "{$route} should not exist — history is retained, not deleted");
        }
    }
}
