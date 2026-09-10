<?php

namespace Tests\Unit\Enums;

use App\Enums\Permission;
use PHPUnit\Framework\TestCase;

class PermissionTest extends TestCase
{
    public function test_values_are_unique_and_dotted(): void
    {
        $values = array_map(fn (Permission $p) => $p->value, Permission::cases());

        $this->assertSame($values, array_unique($values));

        foreach ($values as $value) {
            $this->assertMatchesRegularExpression('/^[a-z]+(\.[a-z-]+)+$/', $value, $value);
        }
    }

    public function test_all_returns_every_case(): void
    {
        $this->assertSame(Permission::cases(), Permission::all());
    }

    public function test_labels_are_present(): void
    {
        foreach (Permission::cases() as $permission) {
            $this->assertNotSame('', $permission->label());
        }
    }
}
