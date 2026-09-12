@props(['active', 'modules' => null])

@php
    $moduleOn = fn (\App\Enums\Module $module) => $modules === null || $modules->enabled($module);
    $tabs = collect([
        ['key' => 'dashboard', 'label' => __('Dashboard'), 'route' => route('student.dashboard'), 'allowed' => true],
        ['key' => 'profile', 'label' => __('My Profile'), 'route' => route('student.profile.edit'), 'allowed' => true],
        ['key' => 'results', 'label' => __('Results'), 'route' => route('student.results.index'), 'allowed' => $moduleOn(\App\Enums\Module::Results)],
        ['key' => 'report-cards', 'label' => __('Report cards'), 'route' => route('student.report-cards.index'), 'allowed' => $moduleOn(\App\Enums\Module::Results)],
        ['key' => 'attendance', 'label' => __('Attendance'), 'route' => route('student.attendance.index'), 'allowed' => $moduleOn(\App\Enums\Module::Attendance)],
        ['key' => 'assignments', 'label' => __('Assignments'), 'route' => route('student.assignments.index'), 'allowed' => $moduleOn(\App\Enums\Module::Assessments)],
        ['key' => 'timetable', 'label' => __('Timetable'), 'route' => route('student.timetable.index'), 'allowed' => $moduleOn(\App\Enums\Module::Timetable)],
        ['key' => 'fees', 'label' => __('Fees'), 'route' => route('student.fees.show'), 'allowed' => $moduleOn(\App\Enums\Module::Fees)],
        ['key' => 'learning-materials', 'label' => __('Learning Materials'), 'route' => route('student.learning-materials.index'), 'allowed' => $moduleOn(\App\Enums\Module::LearningMaterials)],
    ])->filter(fn ($t) => $t['allowed']);
@endphp

<nav class="flex gap-1 overflow-x-auto border-b border-gray-200" aria-label="{{ __('Student portal sections') }}">
    @foreach ($tabs as $tab)
        <a href="{{ $tab['route'] }}"
            @class([
                'shrink-0 rounded-t-md border-b-2 px-3 py-2 text-sm font-medium',
                'border-brand-600 text-brand-700' => $active === $tab['key'],
                'border-transparent text-gray-500 hover:text-gray-700' => $active !== $tab['key'],
            ])>
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
