<?php

namespace Tests\Unit\Enums;

use App\Enums\SchoolStatus;
use PHPUnit\Framework\TestCase;

class SchoolStatusTest extends TestCase
{
    public function test_only_active_allows_access(): void
    {
        $this->assertTrue(SchoolStatus::Active->allowsAccess());
        $this->assertFalse(SchoolStatus::Suspended->allowsAccess());
    }

    public function test_labels(): void
    {
        $this->assertSame('Active', SchoolStatus::Active->label());
        $this->assertSame('Suspended', SchoolStatus::Suspended->label());
    }
}
