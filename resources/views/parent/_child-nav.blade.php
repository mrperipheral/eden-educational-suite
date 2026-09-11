@props(['student', 'siblings', 'active', 'modules' => null, 'sectionRoute' => 'parent.children.show'])

@php
    $moduleOn = fn (\App\Enums\Module $module) => $modules === null || $modules->enabled($module);
    $tabs = collect([
        ['key' => 'profile', 'label' => __('Profile'), 'route' => route('parent.children.show', $student->id), 'allowed' => true],
        ['key' => 'results', 'label' => __('Results'), 'route' => route('parent.results.index', $student->id), 'allowed' => $moduleOn(\App\Enums\Module::Results)],
        ['key' => 'report-cards', 'label' => __('Report cards'), 'route' => route('parent.report-cards.index', $student->id), 'allowed' => $moduleOn(\App\Enums\Module::Results)],
        ['key' => 'attendance', 'label' => __('Attendance'), 'route' => route('parent.attendance.index', $student->id), 'allowed' => $moduleOn(\App\Enums\Module::Attendance)],
        ['key' => 'assignments', 'label' => __('Assignments'), 'route' => route('parent.assignments.index', $student->id), 'allowed' => $moduleOn(\App\Enums\Module::Assessments)],
        ['key' => 'timetable', 'label' => __('Timetable'), 'route' => route('parent.timetable.index', $student->id), 'allowed' => $moduleOn(\App\Enums\Module::Timetable)],
    ])->filter(fn ($t) => $t['allowed']);
@endphp

<div class="space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm">
                <a href="{{ route('parent.dashboard') }}" class="text-brand-600 hover:text-brand-700">{{ __('← My Children') }}</a>
            </p>
            <h2 class="text-lg font-semibold text-gray-900">{{ $student->fullName() }}</h2>
        </div>

        @if ($siblings->count() > 1)
            <div x-data="{ open: false }" class="relative">
                <button type="button" x-on:click="open = ! open"
                    class="flex items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                    {{ __('Switch child') }}
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>
                <div x-show="open" x-cloak x-on:click.outside="open = false"
                    class="absolute right-0 z-10 mt-2 w-56 rounded-md border border-gray-200 bg-white py-1 shadow-lg">
                    @foreach ($siblings as $sibling)
                        <a href="{{ route($sectionRoute, $sibling->id) }}"
                            @class([
                                'block px-3 py-2 text-sm hover:bg-gray-50',
                                'font-semibold text-brand-700' => $sibling->id === $student->id,
                                'text-gray-700' => $sibling->id !== $student->id,
                            ])>
                            {{ $sibling->fullName() }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <nav class="flex gap-1 overflow-x-auto border-b border-gray-200" aria-label="{{ __('Child sections') }}">
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
</div>
