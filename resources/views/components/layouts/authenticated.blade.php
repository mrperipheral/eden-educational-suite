@props([
    'title' => null,
])

@php
    $currentSchool = app(\App\Support\Tenancy\TenantContext::class)->school();
    $modules = app(\App\Support\Modules\SchoolModules::class);
    $moduleOn = fn (\App\Enums\Module $module) => $currentSchool !== null && $modules->enabled($module);

    // A Parent- or Student-role member gets their own portal-scoped nav
    // instead of the admin/staff one — they hold no other permission, so the
    // list below would otherwise render almost empty (see
    // docs/parent-portal.md / docs/student-portal.md).
    $portalRole = $currentSchool !== null ? auth()->user()->roleIn($currentSchool) : null;
    $isParentPortal = $portalRole === \App\Enums\Role::Parent;
    $isStudentPortal = $portalRole === \App\Enums\Role::Student;

    // The notification centre link itself shows the unread count
    // (NotificationController@index) — the shared nav does not run an
    // extra query on every single page load for it (M18, docs/communication.md).
    $notificationLabel = __('Notifications');

    $navLinks = match (true) {
        $isParentPortal => collect([
            ['route' => 'parent.dashboard', 'label' => __('My Children'), 'active' => 'parent.dashboard', 'allowed' => $moduleOn(\App\Enums\Module::ParentPortal) && auth()->user()->can('portal.parent')],
            ['route' => 'parent.profile.edit', 'label' => __('Profile'), 'active' => 'parent.profile.*', 'allowed' => $moduleOn(\App\Enums\Module::ParentPortal) && auth()->user()->can('portal.parent')],
            ['route' => 'parent.announcements.index', 'label' => __('Announcements'), 'active' => 'parent.announcements.*', 'allowed' => $moduleOn(\App\Enums\Module::Notifications) && auth()->user()->can('portal.parent')],
            ['route' => 'parent.notifications.index', 'label' => $notificationLabel, 'active' => 'parent.notifications.*', 'allowed' => $moduleOn(\App\Enums\Module::Notifications) && auth()->user()->can('portal.parent')],
            ['route' => 'settings.profile.edit', 'label' => __('Account settings'), 'active' => 'settings.profile.*', 'allowed' => true],
        ]),
        $isStudentPortal => collect([
            ['route' => 'student.dashboard', 'label' => __('Dashboard'), 'active' => 'student.dashboard', 'allowed' => $moduleOn(\App\Enums\Module::StudentPortal) && auth()->user()->can('portal.student')],
            ['route' => 'student.profile.edit', 'label' => __('My Profile'), 'active' => 'student.profile.*', 'allowed' => $moduleOn(\App\Enums\Module::StudentPortal) && auth()->user()->can('portal.student')],
            ['route' => 'student.results.index', 'label' => __('Results'), 'active' => 'student.results.*', 'allowed' => $moduleOn(\App\Enums\Module::StudentPortal) && auth()->user()->can('portal.student')],
            ['route' => 'student.report-cards.index', 'label' => __('Report cards'), 'active' => 'student.report-cards.*', 'allowed' => $moduleOn(\App\Enums\Module::StudentPortal) && auth()->user()->can('portal.student')],
            ['route' => 'student.attendance.index', 'label' => __('Attendance'), 'active' => 'student.attendance.*', 'allowed' => $moduleOn(\App\Enums\Module::StudentPortal) && auth()->user()->can('portal.student')],
            ['route' => 'student.assignments.index', 'label' => __('Assignments'), 'active' => 'student.assignments.*', 'allowed' => $moduleOn(\App\Enums\Module::StudentPortal) && auth()->user()->can('portal.student')],
            ['route' => 'student.timetable.index', 'label' => __('Timetable'), 'active' => 'student.timetable.*', 'allowed' => $moduleOn(\App\Enums\Module::StudentPortal) && auth()->user()->can('portal.student')],
            ['route' => 'student.learning-materials.index', 'label' => __('Learning Materials'), 'active' => 'student.learning-materials.*', 'allowed' => $moduleOn(\App\Enums\Module::StudentPortal) && $moduleOn(\App\Enums\Module::LearningMaterials) && auth()->user()->can('portal.student')],
            ['route' => 'student.cbt.index', 'label' => __('CBT'), 'active' => 'student.cbt.*', 'allowed' => $moduleOn(\App\Enums\Module::StudentPortal) && $moduleOn(\App\Enums\Module::Cbt) && auth()->user()->can('cbt.take')],
            ['route' => 'student.announcements.index', 'label' => __('Announcements'), 'active' => 'student.announcements.*', 'allowed' => $moduleOn(\App\Enums\Module::Notifications) && auth()->user()->can('portal.student')],
            ['route' => 'student.notifications.index', 'label' => $notificationLabel, 'active' => 'student.notifications.*', 'allowed' => $moduleOn(\App\Enums\Module::Notifications) && auth()->user()->can('portal.student')],
            ['route' => 'settings.profile.edit', 'label' => __('Account settings'), 'active' => 'settings.profile.*', 'allowed' => true],
        ]),
        default => collect([
            ['route' => 'dashboard', 'label' => __('Dashboard'), 'active' => 'dashboard', 'allowed' => true],
            ['route' => 'members.index', 'label' => __('Members'), 'active' => 'members.*', 'allowed' => auth()->user()->can('member.view')],
            ['route' => 'students.index', 'label' => __('Students'), 'active' => 'students.*', 'allowed' => $moduleOn(\App\Enums\Module::Students) && auth()->user()->can('student.view')],
            ['route' => 'guardians.index', 'label' => __('Guardians'), 'active' => 'guardians.*', 'allowed' => $moduleOn(\App\Enums\Module::Guardians) && auth()->user()->can('guardian.view')],
            ['route' => 'promotion.index', 'label' => __('Promotion'), 'active' => 'promotion.*', 'allowed' => $moduleOn(\App\Enums\Module::Promotion) && auth()->user()->can('promotion.view')],
            ['route' => 'teachers.index', 'label' => __('Teachers'), 'active' => 'teachers.*', 'allowed' => $moduleOn(\App\Enums\Module::Staff) && auth()->user()->can('staff.view')],
            ['route' => 'timetables.index', 'label' => __('Timetable'), 'active' => 'timetables.*', 'allowed' => $moduleOn(\App\Enums\Module::Timetable) && auth()->user()->can('timetable.view')],
            ['route' => 'attendance.index', 'label' => __('Attendance'), 'active' => 'attendance.*', 'allowed' => $moduleOn(\App\Enums\Module::Attendance) && auth()->user()->can('attendance.view')],
            ['route' => 'assessments.index', 'label' => __('Assessments'), 'active' => 'assessments.*', 'allowed' => $moduleOn(\App\Enums\Module::Assessments) && auth()->user()->can('assessment.view')],
            ['route' => 'results.runs.index', 'label' => __('Results'), 'active' => 'results.*', 'allowed' => $moduleOn(\App\Enums\Module::Results) && auth()->user()->can('result.view')],
            ['route' => 'fees.index', 'label' => __('Fees'), 'active' => 'fees.*', 'allowed' => $moduleOn(\App\Enums\Module::Fees) && auth()->user()->can('fees.report')],
            ['route' => 'learning-materials.index', 'label' => __('Learning Materials'), 'active' => 'learning-materials.*', 'allowed' => $moduleOn(\App\Enums\Module::LearningMaterials) && auth()->user()->can('material.view')],
            ['route' => 'cbt.examinations.index', 'label' => __('CBT'), 'active' => 'cbt.*', 'allowed' => $moduleOn(\App\Enums\Module::Cbt) && auth()->user()->can('cbt.view')],
            ['route' => 'academic.sessions.index', 'label' => __('Academic'), 'active' => 'academic.*', 'allowed' => $moduleOn(\App\Enums\Module::Academics) && auth()->user()->can('academics.view')],
            ['route' => 'communication.threads.index', 'label' => __('Communication'), 'active' => 'communication.*', 'allowed' => $moduleOn(\App\Enums\Module::Notifications) && auth()->user()->can('communication.view')],
            ['route' => 'announcements.index', 'label' => __('Announcements'), 'active' => 'announcements.*', 'allowed' => $moduleOn(\App\Enums\Module::Notifications) && auth()->user()->can('announcement.view')],
            ['route' => 'notifications.index', 'label' => $notificationLabel, 'active' => 'notifications.*', 'allowed' => $moduleOn(\App\Enums\Module::Notifications)],
            ['route' => 'settings.school.edit', 'label' => __('School settings'), 'active' => 'settings.school.*', 'allowed' => auth()->user()->can('school.settings.view')],
            ['route' => 'admin.schools.index', 'label' => __('Schools'), 'active' => 'admin.schools.*', 'allowed' => auth()->user()->can('viewAny', \App\Models\School::class)],
            ['route' => 'settings.profile.edit', 'label' => __('Account settings'), 'active' => 'settings.profile.*', 'allowed' => true],
        ]),
    };

    $navLinks = $navLinks->filter(fn ($link) => $link['allowed']);

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
