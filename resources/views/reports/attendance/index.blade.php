@php
    $selectClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
    $tabs = ['student' => __('Student attendance'), 'class' => __('Class / arm attendance'), 'trend' => __('Trend')];
@endphp

<x-layouts.authenticated :title="__('Attendance Reports')">
    <x-slot:actions>
        @can('reports.export')
            <x-button :href="route('reports.attendance.export', array_merge($filters, ['tab' => $tab]))" size="sm" variant="secondary">{{ __('Export CSV') }}</x-button>
        @endcan
    </x-slot:actions>

    <div class="space-y-6">
        <p class="text-sm"><a href="{{ route('reports.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Reports') }}</a></p>

        <nav class="flex flex-wrap gap-2 border-b border-gray-200 pb-2">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('reports.attendance.index', array_merge($filters, ['tab' => $key])) }}"
                    @class(['rounded-md px-3 py-1.5 text-sm font-medium', 'bg-brand-50 text-brand-700' => $tab === $key, 'text-gray-600 hover:bg-gray-100' => $tab !== $key])>
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <form method="GET" action="{{ route('reports.attendance.index') }}" class="flex flex-wrap items-end gap-2" x-data="{ levelId: '{{ $filters['level'] ?? '' }}' }">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="space-y-1">
                <label for="session" class="block text-xs font-medium text-gray-700">{{ __('Session') }}</label>
                <select id="session" name="session" class="{{ $selectClass }}">
                    <option value="">{{ __('All sessions') }}</option>
                    @foreach ($sessions as $session)
                        <option value="{{ $session->id }}" @selected(($filters['session'] ?? null) == $session->id)>{{ $session->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="space-y-1">
                <label for="level" class="block text-xs font-medium text-gray-700">{{ __('Level') }}</label>
                <select id="level" name="level" x-model="levelId" class="{{ $selectClass }}">
                    <option value="">{{ __('All levels') }}</option>
                    @foreach ($levels as $level)
                        <option value="{{ $level->id }}">{{ $level->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="space-y-1">
                <label for="arm" class="block text-xs font-medium text-gray-700">{{ __('Arm') }}</label>
                <select id="arm" name="arm" class="{{ $selectClass }}">
                    <option value="">{{ __('All arms') }}</option>
                    @foreach ($levels as $level)
                        @foreach ($level->arms as $arm)
                            <option value="{{ $arm->id }}" x-show="levelId === '{{ $level->id }}'" @selected(($filters['arm'] ?? null) == $arm->id)>{{ $level->name }} — {{ $arm->name }}</option>
                        @endforeach
                    @endforeach
                </select>
            </div>
            <div class="space-y-1">
                <label for="from" class="block text-xs font-medium text-gray-700">{{ __('From') }}</label>
                <input type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}" class="{{ $selectClass }}">
            </div>
            <div class="space-y-1">
                <label for="to" class="block text-xs font-medium text-gray-700">{{ __('To') }}</label>
                <input type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}" class="{{ $selectClass }}">
            </div>
            <x-button type="submit" size="sm" variant="secondary">{{ __('Filter') }}</x-button>
            @if (array_filter($filters))
                <x-button :href="route('reports.attendance.index', ['tab' => $tab])" size="sm" variant="ghost">{{ __('Clear') }}</x-button>
            @endif
        </form>

        @if ($tab === 'student')
            @if ($studentAttendance->isEmpty())
                <x-empty-state :title="__('No attendance data matches')" :description="__('Only submitted registers are counted.')" />
            @else
                <x-card :padding="false">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead><tr class="text-left text-xs font-medium text-gray-500">
                            <th class="px-4 py-2">{{ __('Student') }}</th><th class="px-4 py-2">{{ __('Admission #') }}</th>
                            <th class="px-4 py-2">{{ __('Marked') }}</th><th class="px-4 py-2">{{ __('Present') }}</th>
                            <th class="px-4 py-2">{{ __('Absent') }}</th><th class="px-4 py-2">{{ __('Late') }}</th><th class="px-4 py-2">{{ __('Excused') }}</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($studentAttendance as $row)
                                <tr>
                                    <td class="px-4 py-2">{{ $row->student?->fullName() }}</td>
                                    <td class="px-4 py-2">{{ $row->student?->admission_number }}</td>
                                    <td class="px-4 py-2">{{ $row->days_marked }}</td>
                                    <td class="px-4 py-2">{{ $row->days_present }}</td>
                                    <td class="px-4 py-2">{{ $row->days_absent }}</td>
                                    <td class="px-4 py-2">{{ $row->days_late }}</td>
                                    <td class="px-4 py-2">{{ $row->days_excused }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                </x-card>
                {{ $studentAttendance->links() }}
            @endif
        @elseif ($tab === 'class')
            @if ($classAttendance->isEmpty())
                <x-empty-state :title="__('No class attendance data matches')" />
            @else
                <x-card :padding="false">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead><tr class="text-left text-xs font-medium text-gray-500">
                            <th class="px-4 py-2">{{ __('Level') }}</th><th class="px-4 py-2">{{ __('Arm') }}</th>
                            <th class="px-4 py-2">{{ __('Registers') }}</th><th class="px-4 py-2">{{ __('Absences') }}</th>
                            <th class="px-4 py-2">{{ __('Attendance %') }}</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($classAttendance as $row)
                                <tr>
                                    <td class="px-4 py-2">{{ $row['level_name'] }}</td>
                                    <td class="px-4 py-2">{{ $row['arm_name'] }}</td>
                                    <td class="px-4 py-2">{{ $row['registers_submitted'] }}</td>
                                    <td class="px-4 py-2">{{ $row['absence_marks'] }}</td>
                                    <td class="px-4 py-2">{{ $row['attendance_percentage'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                </x-card>
            @endif
        @else
            @if ($trend->isEmpty())
                <x-empty-state :title="__('No attendance trend data matches')" />
            @else
                <x-card :padding="false">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead><tr class="text-left text-xs font-medium text-gray-500">
                            <th class="px-4 py-2">{{ __('Date') }}</th><th class="px-4 py-2">{{ __('Marked') }}</th>
                            <th class="px-4 py-2">{{ __('Present') }}</th><th class="px-4 py-2">{{ __('Attendance %') }}</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($trend as $row)
                                <tr>
                                    <td class="px-4 py-2">{{ $row['attendance_date'] }}</td>
                                    <td class="px-4 py-2">{{ $row['total_marks'] }}</td>
                                    <td class="px-4 py-2">{{ $row['present_marks'] }}</td>
                                    <td class="px-4 py-2">
                                        <div class="flex items-center gap-2">
                                            <div class="h-2 w-24 rounded-full bg-gray-100">
                                                <div class="h-2 rounded-full bg-brand-500" style="width: {{ min(100, (float) $row['attendance_percentage']) }}%"></div>
                                            </div>
                                            <span>{{ $row['attendance_percentage'] }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                </x-card>
            @endif
        @endif
    </div>
</x-layouts.authenticated>
