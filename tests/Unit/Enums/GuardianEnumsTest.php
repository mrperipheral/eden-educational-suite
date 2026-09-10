<?php

namespace Tests\Unit\Enums;

use App\Enums\GuardianRelationship;
use Tests\TestCase;

class GuardianEnumsTest extends TestCase
{
    public function test_relationship_values_and_labels(): void
    {
        $this->assertSame(
            ['mother', 'father', 'grandparent', 'aunt_uncle', 'sibling', 'legal_guardian', 'other'],
            array_map(fn (GuardianRelationship $r) => $r->value, GuardianRelationship::all()),
        );

        foreach (GuardianRelationship::all() as $relationship) {
            $this->assertNotSame('', $relationship->label());
        }
    }
}
