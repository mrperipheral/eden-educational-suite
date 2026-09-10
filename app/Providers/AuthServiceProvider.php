<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\SchoolUser;
use App\Policies\MembershipPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the roles-and-permissions foundation into Laravel's Gate.
 *
 * Every Permission becomes a Gate ability whose check delegates to
 * `User::hasPermission()` — which is composed with the active `TenantContext`.
 * So `$user->can('student.view')`, `@can('member.view')`, `->can('finance.manage')`
 * route middleware and `$this->authorize(...)` in controllers all resolve
 * *within the school the user is currently working in*, and return false when
 * there is no active school.
 *
 * There is deliberately **no `Gate::before()`** — platform-wide bypass does not
 * exist. A platform admin's extra reach is expressed inside `hasPermission()`
 * and only ever applies to the one school they have entered.
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    private array $policies = [
        SchoolUser::class => MembershipPolicy::class,
    ];

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        foreach (Permission::cases() as $permission) {
            Gate::define(
                $permission->value,
                fn ($user) => $user->hasPermission($permission),
            );
        }
    }
}
