<?php

namespace Tests\Unit\Auth;

use App\Enums\UserStatus;
use PHPUnit\Framework\TestCase;

class UserStatusTest extends TestCase
{
    public function test_only_active_allows_authentication(): void
    {
        $this->assertTrue(UserStatus::Active->allowsAuthentication());
        $this->assertFalse(UserStatus::Suspended->allowsAuthentication());
        $this->assertFalse(UserStatus::Disabled->allowsAuthentication());
    }

    public function test_blocked_statuses_expose_a_generic_message(): void
    {
        $this->assertNotSame('', UserStatus::Suspended->authenticationBlockedMessage());
        $this->assertNotSame('', UserStatus::Disabled->authenticationBlockedMessage());
        $this->assertSame('', UserStatus::Active->authenticationBlockedMessage());
    }

    public function test_labels(): void
    {
        $this->assertSame('Active', UserStatus::Active->label());
        $this->assertSame('Suspended', UserStatus::Suspended->label());
    }
}
