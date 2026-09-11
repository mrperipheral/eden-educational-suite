<x-layouts.authenticated :title="__('Attendance — :name', ['name' => $student->fullName()])">
    <div class="space-y-6">
        @include('parent._child-nav', [
            'student' => $student, 'siblings' => $siblings, 'active' => 'attendance',
            'modules' => $modules, 'sectionRoute' => 'parent.attendance.index',
        ])

        @if (! $moduleOn)
            <x-empty-state
                :title="__('Attendance is not currently available')"
                :description="__('This school has not enabled attendance for this account yet.')"
            />
        @elseif ($summaries->isEmpty())
            <x-empty-state
                :title="__('No attendance information is available yet')"
                :description="__('Attendance appears here once the school has submitted a register that includes this student.')"
            />
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach ($summaries as $row)
                    <x-card :title="$row['period']->session?->name.' — '.$row['period']->name">
                        <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                            <dt class="text-gray-500">{{ __('Days opened') }}</dt>
                            <dd class="text-gray-900">{{ $row['summary']['opened'] }}</dd>
                            <dt class="text-gray-500">{{ __('Present') }}</dt>
                            <dd class="text-gray-900">{{ $row['summary']['present'] }}</dd>
                            <dt class="text-gray-500">{{ __('Absent') }}</dt>
                            <dd class="text-gray-900">{{ $row['summary']['absent'] }}</dd>
                            <dt class="text-gray-500">{{ __('Attendance %') }}</dt>
                            <dd class="text-gray-900">{{ $row['summary']['percentage'] }}%</dd>
                        </dl>
                    </x-card>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.authenticated>
