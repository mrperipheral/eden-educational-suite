<x-layouts.authenticated :title="__('Dashboard')">
    <div class="space-y-6">
        <x-greeting :context="__('Here\'s what\'s on today.')" />

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        @include('student._nav', ['active' => 'dashboard'])

        @if (! $student)
            <x-empty-state
                :title="__('Your account is not linked to a student record yet')"
                :description="__('Please contact your school administrator.')"
            />
        @else
            <x-card>
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-lg font-semibold text-gray-900">{{ $student->fullName() }}</p>
                        <p class="font-mono text-xs text-gray-500">{{ $student->admission_number }}</p>
                    </div>
                    <x-badge :variant="$student->status->badgeVariant()">{{ $student->status->label() }}</x-badge>
                </div>

                @if ($student->currentEnrollment)
                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-gray-500 sm:grid-cols-4">
                        <div>{{ __('Class') }}: <span class="text-gray-900">
                            {{ $student->currentEnrollment->level?->name }}
                            @if ($student->currentEnrollment->arm) — {{ $student->currentEnrollment->arm->name }} @endif
                        </span></div>
                        <div>{{ __('Session') }}: <span class="text-gray-900">{{ $student->currentEnrollment->session?->name }}</span></div>
                        @if ($student->currentEnrollment->period)
                            <div>{{ __('Term') }}: <span class="text-gray-900">{{ $student->currentEnrollment->period->name }}</span></div>
                        @endif
                    </dl>
                @else
                    <p class="mt-3 text-sm italic text-gray-500">{{ __('Not currently enrolled in a class.') }}</p>
                @endif
            </x-card>

            @isset($todayClasses)
                <x-card :title="__('Your classes today')">
                    @if ($todayClasses->isEmpty())
                        <x-empty-state :title="__('No classes scheduled for today')" />
                    @else
                        <ul class="divide-y divide-gray-100">
                            @foreach ($todayClasses as $entry)
                                <li class="flex items-center justify-between gap-3 py-2 text-sm">
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-gray-900">{{ $entry->subject?->name }}</p>
                                        <p class="truncate text-xs text-gray-500">{{ $entry->teacher?->fullName() }}</p>
                                    </div>
                                    <span class="shrink-0 font-mono text-xs text-gray-500">{{ $entry->start_time }}–{{ $entry->end_time }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            @endisset

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-card :title="__('Results')">
                    <p class="text-xs text-gray-500">{{ __('View your published results and report cards.') }}</p>
                    <div class="mt-3"><x-button :href="route('student.results.index')" size="sm" variant="secondary">{{ __('Open') }}</x-button></div>
                </x-card>
                <x-card :title="__('Attendance')">
                    <p class="text-xs text-gray-500">{{ __('See your attendance record per term.') }}</p>
                    <div class="mt-3"><x-button :href="route('student.attendance.index')" size="sm" variant="secondary">{{ __('Open') }}</x-button></div>
                </x-card>
                <x-card :title="__('Assignments')">
                    <p class="text-xs text-gray-500">{{ __('See your class assignments.') }}</p>
                    <div class="mt-3"><x-button :href="route('student.assignments.index')" size="sm" variant="secondary">{{ __('Open') }}</x-button></div>
                </x-card>
                <x-card :title="__('Timetable')">
                    <p class="text-xs text-gray-500">{{ __('See your class timetable.') }}</p>
                    <div class="mt-3"><x-button :href="route('student.timetable.index')" size="sm" variant="secondary">{{ __('Open') }}</x-button></div>
                </x-card>
            </div>
        @endif
    </div>
</x-layouts.authenticated>
