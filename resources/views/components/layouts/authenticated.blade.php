@props([
    'title' => null,
])

@php
    $currentSchool = app(\App\Support\Tenancy\TenantContext::class)->school();
    $modules = app(\App\Support\Modules\SchoolModules::class);
    $moduleOn = fn (\App\Enums\Module $module) => $currentSchool !== null && $modules->enabled($module);

    $navLinks = collect([
        ['route' => 'dashboard', 'label' => __('Dashboard'), 'active' => 'dashboard', 'allowed' => true],
        ['route' => 'members.index', 'label' => __('Members'), 'active' => 'members.*', 'allowed' => auth()->user()->can('member.view')],
        ['route' => 'students.index', 'label' => __('Students'), 'active' => 'students.*', 'allowed' => $moduleOn(\App\Enums\Module::Students) && auth()->user()->can('student.view')],
        ['route' => 'guardians.index', 'label' => __('Guardians'), 'active' => 'guardians.*', 'allowed' => $moduleOn(\App\Enums\Module::Guardians) && auth()->user()->can('guardian.view')],
        ['route' => 'academic.sessions.index', 'label' => __('Academic'), 'active' => 'academic.*', 'allowed' => $moduleOn(\App\Enums\Module::Academics) && auth()->user()->can('academics.view')],
        ['route' => 'settings.school.edit', 'label' => __('School settings'), 'active' => 'settings.school.*', 'allowed' => auth()->user()->can('school.settings.view')],
        ['route' => 'admin.schools.index', 'label' => __('Schools'), 'active' => 'admin.schools.*', 'allowed' => auth()->user()->can('viewAny', \App\Models\School::class)],
        ['route' => 'settings.profile.edit', 'label' => __('Account settings'), 'active' => 'settings.profile.*', 'allowed' => true],
    ])->filter(fn ($link) => $link['allowed']);

    $canSwitchSchool = $currentSchool !== null
        && (auth()->user()->isPlatformAdmin() || auth()->user()->schools()->count() > 1);
@endphp

<x-layouts.app :title="$title">
    <x-slot:navigation>
        @foreach ($navLinks as $link)
            @php($isActive = request()->routeIs($link['active']))
            <a
                href="{{ route($link['route']) }}"
                @class([
                    'rounded-md px-3 py-2 text-sm font-medium',
                    'bg-brand-50 text-brand-700' => $isActive,
                    'text-gray-700 hover:bg-gray-100' => ! $isActive,
                ])
                @if ($isActive) aria-current="page" @endif
            >
                {{ $link['label'] }}
            </a>
        @endforeach
    </x-slot:navigation>

    <x-slot:headerActions>
        @if ($currentSchool)
            <div class="hidden items-center gap-2 sm:flex">
                <x-badge variant="brand">{{ $currentSchool->name }}</x-badge>
                @if ($canSwitchSchool)
                    <a href="{{ route('school-context.create') }}" class="text-xs font-medium text-brand-600 hover:text-brand-700">
                        {{ __('Switch') }}
                    </a>
                @endif
            </div>
        @endif

        <x-dropdown>
            <x-slot:trigger>
                <button type="button" class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-gray-700 hover:bg-gray-100">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">
                        {{ str(auth()->user()->name)->trim()->substr(0, 1)->upper() }}
                    </span>
                    <span class="hidden sm:inline">{{ auth()->user()->name }}</span>
                </button>
            </x-slot:trigger>

            <div class="border-b border-gray-100 px-3 py-2">
                <p class="truncate text-sm font-medium text-gray-900">{{ auth()->user()->name }}</p>
                <p class="truncate text-xs text-gray-500">{{ auth()->user()->email }}</p>
                @if (auth()->user()->isPlatformAdmin())
                    <span class="mt-1 inline-block"><x-badge variant="gray">{{ __('Platform admin') }}</x-badge></span>
                @endif
            </div>
            @if ($currentSchool)
                <a href="{{ route('school-context.create') }}" class="block px-3 py-2 text-sm text-gray-700 hover:bg-gray-50" role="menuitem">
                    {{ __('Switch school') }}
                </a>
            @endif
            <a href="{{ route('settings.profile.edit') }}" class="block px-3 py-2 text-sm text-gray-700 hover:bg-gray-50" role="menuitem">
                {{ __('Account settings') }}
            </a>
            <form method="POST" action="{{ route('logout') }}" role="none">
                @csrf
                <button type="submit" class="block w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50" role="menuitem">
                    {{ __('Sign out') }}
                </button>
            </form>
        </x-dropdown>
    </x-slot:headerActions>

    @if ($title)
        <x-page-header :title="$title">
            @isset($actions)
                <x-slot:actions>{{ $actions }}</x-slot:actions>
            @endisset
        </x-page-header>
    @endif

    {{ $slot }}
</x-layouts.app>
