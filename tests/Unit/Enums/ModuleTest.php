<?php

namespace Tests\Unit\Enums;

use App\Enums\Module;
use Tests\TestCase;

class ModuleTest extends TestCase
{
    public function test_every_module_has_a_label_description_and_group(): void
    {
        foreach (Module::cases() as $module) {
            $this->assertNotSame('', $module->label(), $module->value);
            $this->assertNotSame('', $module->description(), $module->value);
            $this->assertNotSame('', $module->group(), $module->value);
        }
    }

    public function test_dependencies_are_modules_and_never_self_referential(): void
    {
        foreach (Module::cases() as $module) {
            foreach ($module->dependencies() as $dependency) {
                $this->assertInstanceOf(Module::class, $dependency);
                $this->assertNotSame($module, $dependency, "{$module->value} depends on itself");
            }
        }
    }

    public function test_the_dependency_graph_is_acyclic(): void
    {
        $visiting = [];
        $done = [];

        $visit = function (Module $module) use (&$visit, &$visiting, &$done): void {
            if (isset($done[$module->value])) {
                return;
            }

            $this->assertArrayNotHasKey($module->value, $visiting, "dependency cycle through {$module->value}");
            $visiting[$module->value] = true;

            foreach ($module->dependencies() as $dependency) {
                $visit($dependency);
            }

            unset($visiting[$module->value]);
            $done[$module->value] = true;
        };

        foreach (Module::cases() as $module) {
            $visit($module);
        }

        $this->assertCount(count(Module::cases()), $done);
    }

    public function test_default_enabled_modules_have_all_dependencies_default_enabled(): void
    {
        $defaults = Module::defaults();

        foreach (Module::cases() as $module) {
            if (! $module->enabledByDefault()) {
                continue;
            }

            foreach ($module->dependencies() as $dependency) {
                $this->assertTrue(
                    $defaults[$dependency->value],
                    "{$module->value} is on by default but its dependency {$dependency->value} is not",
                );
            }
        }
    }

    public function test_only_shipped_modules_report_as_available(): void
    {
        // Each domain milestone flips its own module to available. As of M9:
        // Academic Foundation (M8) and Student Management (M9); the rest Planned.
        $available = ['academics', 'students'];

        foreach (Module::cases() as $module) {
            $this->assertSame(
                in_array($module->value, $available, true),
                $module->isAvailable(),
                $module->value,
            );
        }
    }

    public function test_grouped_lists_every_module_exactly_once_in_case_order(): void
    {
        $flat = [];

        foreach (Module::grouped() as $group => $modules) {
            $this->assertNotSame('', $group);
            foreach ($modules as $module) {
                $flat[] = $module;
            }
        }

        $this->assertEqualsCanonicalizing(Module::cases(), $flat);
        $this->assertCount(count(Module::cases()), $flat, 'a module appears in more than one group');
    }

    public function test_defaults_covers_every_module(): void
    {
        $this->assertEqualsCanonicalizing(
            array_map(fn (Module $m) => $m->value, Module::cases()),
            array_keys(Module::defaults()),
        );
    }
}
