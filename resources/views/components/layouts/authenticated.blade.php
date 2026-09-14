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
            ['route' => 'entry-assessments.index', 'label' => __('Entry Assessment'), 'active' => 'entry-assessments.*', 'allowed' => $moduleOn(\App\Enums\Module::EntryAssessment) && auth()->user()->can('placement.view')],
            ['route' => 'reports.index', 'label' => __('Reports'), 'active' => 'reports.*', 'allowed' => $moduleOn(\App\Enums\Module::Reports) && auth()->user()->can('reports.view')],
            ['route' => 'academic.sessions.index', 'label' => __('Academic'), 'active' => 'academic.*', 'allowed' => $moduleOn(\App\Enums\Module::Academics) && auth()->user()->can('academics.view')],
            ['route' => 'communication.threads.index', 'label' => __('Communication'), 'active' => 'communication.*', 'allowed' => $moduleOn(\App\Enums\Module::Notifications) && auth()->user()->can('communication.view')],
            ['route' => 'announcements.index', 'label' => __('Announcements'), 'active' => 'announcements.*', 'allowed' => $moduleOn(\App\Enums\Module::Notifications) && auth()->user()->can('announcement.view')],
            ['route' => 'notifications.index', 'label' => $notificationLabel, 'active' => 'notifications.*', 'allowed' => $moduleOn(\App\Enums\Module::Notifications)],
            ['route' => 'settings.school.edit', 'label' => __('School settings'), 'active' => 'settings.school.*', 'allowed' => auth()->user()->can('school.settings.view')],
            ['route' => 'audit-log.index', 'label' => __('Audit Log'), 'active' => 'audit-log.*', 'allowed' => auth()->user()->can('audit.view')],
            ['route' => 'admin.schools.index', 'label' => __('Schools'), 'active' => 'admin.schools.*', 'allowed' => auth()->user()->can('viewAny', \App\Models\School::class)],
            ['route' => 'admin.reports.index', 'label' => __('Platform Reports'), 'active' => 'admin.reports.*', 'allowed' => auth()->user()->can('viewAny', \App\Models\School::class)],
            ['route' => 'settings.profile.edit', 'label' => __('Account settings'), 'active' => 'settings.profile.*', 'allowed' => true],
        ]),
    };

    $navLinks = $navLinks->filter(fn ($link) => $link['allowed'])->values();

    // Keep the first handful of items always visible; the rest sit behind a
    // "See more" toggle so a long, permission-filtered menu doesn't force
    // scrolling on a phone before the most-used items are reachable. The
    // toggle starts open if the current page happens to live in it.
    $primaryNavCount = ($isParentPortal || $isStudentPortal) ? 6 : 7;
    $primaryNavLinks = $navLinks->take($primaryNavCount);
    $moreNavLinks = $navLinks->slice($primaryNavCount)->values();
    $moreNavHasActive = $moreNavLinks->contains(fn ($link) => request()->routeIs($link['active']));

    $canSwitchSchool = $currentSchool !== null
        && (auth()->user()->isPlatformAdmin() || auth()->user()->schools()->count() > 1);

    $settings = $currentSchool?->settings;
    $schoolLogoUrl = $settings?->hasLogo() ? route('settings.school.branding.logo.show') : null;
    $schoolCoverUrl = $settings?->hasCover() ? route('settings.school.branding.cover.show') : null;
@endphp

@php
    $navLinkClasses = fn (array $link) => [
        'flex items-center rounded-md px-3 py-2.5 text-sm font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 lg:py-2',
        'bg-brand-50 text-brand-700' => request()->routeIs($link['active']),
        'text-gray-700 hover:bg-gray-100' => ! request()->routeIs($link['active']),
    ];
@endphp

<x-layouts.app :title="$title">
    <x-slot:brandHeader>
        @if ($currentSchool)
            <div
                class="relative flex h-20 shrink-0 items-center gap-3 overflow-hidden border-b border-gray-200 px-4"
                @if ($schoolCoverUrl) style="background-image: linear-gradient(to bottom, rgba(17,24,39,.6), rgba(17,24,39,.6)), url('{{ $schoolCoverUrl }}'); background-size: cover; background-position: center;" @endif
            >
                @if ($settings?->brand_color)
                    <span class="absolute inset-x-0 top-0 h-1" style="background-color: {{ $settings->brand_color }}"></span>
                @endif
                @if ($schoolLogoUrl)
                    <img src="{{ $schoolLogoUrl }}" alt="" class="h-10 w-10 shrink-0 rounded-md bg-white object-contain p-0.5 ring-1 ring-black/5">
                @endif
                <div class="min-w-0 flex-1">
                    <p @class(['truncate text-sm font-semibold', $schoolCoverUrl ? 'text-white' : 'text-gray-900'])>{{ $currentSchool->name }}</p>
                    <p @class(['text-[11px]', $schoolCoverUrl ? 'text-gray-200' : 'text-gray-500'])>{{ __('School Portal') }}</p>
                </div>
            </div>
            <p class="shrink-0 border-b border-gray-100 px-4 py-1.5 text-center text-[10px] tracking-wide text-gray-400">
                {{ __('Powered by Eden Education Suite') }}
            </p>
        @else
            <div class="flex h-16 shrink-0 items-center gap-2 border-b border-gray-200 px-6">
                <span class="text-lg font-semibold text-brand-700">{{ config('app.name') }}</span>
            </div>
            @if (auth()->user()->isPlatformAdmin())
                <p class="shrink-0 border-b border-gray-100 px-4 py-1.5 text-center text-[10px] font-medium uppercase tracking-wide text-gray-400">
                    {{ __('Platform Administration') }}
                </p>
            @endif
        @endif
    </x-slot:brandHeader>

    <x-slot:navigation>
        @foreach ($primaryNavLinks as $link)
            <a href="{{ route($link['route']) }}" @class($navLinkClasses($link)) @if (request()->routeIs($link['active'])) aria-current="page" @endif>
                {{ $link['label'] }}
            </a>
        @endforeach

        @if ($moreNavLinks->isNotEmpty())
            <div x-data="{ moreOpen: @js($moreNavHasActive) }">
                <button
                    type="button"
                    @click="moreOpen = ! moreOpen"
                    class="flex w-full items-center justify-between rounded-md px-3 py-2.5 text-sm font-medium text-gray-500 hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 lg:py-2"
                    :aria-expanded="moreOpen"
                    aria-controls="primary-nav-more"
                >
                    <span x-text="moreOpen ? '{{ __('See less') }}' : '{{ __('See more') }}'"></span>
                    <svg class="h-4 w-4 shrink-0 transition-transform" :class="moreOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>
                <div id="primary-nav-more" x-show="moreOpen" x-transition x-cloak class="flex flex-col gap-1 pt-1">
                    @foreach ($moreNavLinks as $link)
                        <a href="{{ route($link['route']) }}" @class($navLinkClasses($link)) @if (request()->routeIs($link['active'])) aria-current="page" @endif>
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </x-slot:navigation>

    <x-slot:headerActions>
        @if ($currentSchool)
            <div class="hidden items-center gap-2 sm:flex">
                <x-badge variant="brand">{{ $currentSchool->name }}</x-badge>
                @if ($canSwitchSchool)
                    <a href="{{ route('school-context.create') }}" class="text-xs font-medium text-brand-600 hover:text-brand-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
                        {{ __('Switch') }}
                    </a>
                @endif
            </div>
        @endif

        <x-dropdown>
            <x-slot:trigger>
                <button type="button" class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-gray-700 hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500" aria-label="{{ __('Account menu for :name', ['name' => auth()->user()->name]) }}">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">
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
