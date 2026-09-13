@php
    $selectClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
    $tabs = ['runs' => __('Result runs'), 'student' => __('Student performance'), 'subject' => __('Subject performance'), 'class' => __('Class / arm performance')];
@endphp

<x-layouts.authenticated :title="__('Academic Reports')">
    <x-slot:actions>
        @can('reports.export')
            <x-button :href="route('reports.academic.export', array_merge($filters, ['tab' => $tab]))" size="sm" variant="secondary">{{ __('Export CSV') }}</x-button>
        @endcan
    </x-slot:actions>

    <div class="space-y-6">
        <p class="text-sm"><a href="{{ route('reports.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Reports') }}</a></p>

        <nav class="flex flex-wrap gap-2 border-b border-gray-200 pb-2">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('reports.academic.index', array_merge($filters, ['tab' => $key])) }}"
                    @class(['rounded-md px-3 py-1.5 text-sm font-medium', 'bg-brand-50 text-brand-700' => $tab === $key, 'text-gray-600 hover:bg-gray-100' => $tab !== $key])>
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <form method="GET" action="{{ route('reports.academic.index') }}" class="flex flex-wrap items-end gap-2" x-data="{ levelId: '{{ $filters['level'] ?? '' }}' }">
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
            @if ($tab === 'runs')
                <div class="space-y-1">
                    <label for="status" class="block text-xs font-medium text-gray-700">{{ __('Status') }}</label>
                    <select id="status" name="status" class="{{ $selectClass }}">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach (\App\Enums\ResultRunStatus::all() as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <x-button type="submit" size="sm" variant="secondary">{{ __('Filter') }}</x-button>
            @if (array_filter($filters))
                <x-button :href="route('reports.academic.index', ['tab' => $tab])" size="sm" variant="ghost">{{ __('Clear') }}</x-button>
            @endif
        </form>

        @if ($tab === 'runs')
            @if ($runs->isEmpty())
                <x-empty-state :title="__('No result runs match')" />
            @else
                <x-card :padding="false">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead><tr class="text-left text-xs font-medium text-gray-500">
                            <th class="px-4 py-2">{{ __('Session') }}</th><th class="px-4 py-2">{{ __('Period') }}</th>
                            <th class="px-4 py-2">{{ __('Level') }}</th><th class="px-4 py-2">{{ __('Arm') }}</th>
                            <th class="px-4 py-2">{{ __('Status') }}</th><th class="px-4 py-2">{{ __('Students') }}</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($runs as $run)
                                <tr>
                                    <td class="px-4 py-2">{{ $run->session?->name }}</td>
                                    <td class="px-4 py-2">{{ $run->period?->name }}</td>
                                    <td class="px-4 py-2">{{ $run->level?->name }}</td>
                                    <td class="px-4 py-2">{{ $run->arm?->name }}</td>
                                    <td class="px-4 py-2"><x-badge :variant="$run->status->badgeVariant()">{{ $run->status->label() }}</x-badge></td>
                                    <td class="px-4 py-2">{{ $run->student_results_count }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-card>
                {{ $runs->links() }}
            @endif
        @elseif ($tab === 'student')
            @if ($studentResults->isEmpty())
                <x-empty-state :title="__('No student results match')" :description="__('Only published or locked result runs appear here.')" />
            @else
                <x-card :padding="false">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead><tr class="text-left text-xs font-medium text-gray-500">
                            <th class="px-4 py-2">{{ __('Student') }}</th><th class="px-4 py-2">{{ __('Admission #') }}</th>
                            <th class="px-4 py-2">{{ __('Average %') }}</th><th class="px-4 py-2">{{ __('Position') }}</th>
                            <th class="px-4 py-2">{{ __('Class size') }}</th><th class="px-4 py-2">{{ __('Grade') }}</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($studentResults as $row)
                                <tr>
                                    <td class="px-4 py-2">{{ $row->student?->fullName() }}</td>
                                    <td class="px-4 py-2">{{ $row->student?->admission_number }}</td>
                                    <td class="px-4 py-2">{{ $row->average_percentage }}</td>
                                    <td class="px-4 py-2">{{ $row->position }}</td>
                                    <td class="px-4 py-2">{{ $row->class_size }}</td>
                                    <td class="px-4 py-2">{{ $row->overall_grade_code_snapshot }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-card>
                {{ $studentResults->links() }}
            @endif
        @elseif ($tab === 'subject')
            @if ($subjectResults->isEmpty())
                <x-empty-state :title="__('No subject performance data matches')" />
            @else
                <x-card :padding="false">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead><tr class="text-left text-xs font-medium text-gray-500">
                            <th class="px-4 py-2">{{ __('Subject') }}</th><th class="px-4 py-2">{{ __('Assessed') }}</th>
                            <th class="px-4 py-2">{{ __('Average %') }}</th><th class="px-4 py-2">{{ __('Grade distribution') }}</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($subjectResults as $row)
                                <tr>
                                    <td class="px-4 py-2">{{ $row['subject_name'] }}</td>
                                    <td class="px-4 py-2">{{ $row['students_assessed'] }}</td>
                                    <td class="px-4 py-2">{{ $row['average_percentage'] }}</td>
                                    <td class="px-4 py-2 text-xs text-gray-500">
                                        @foreach ($row['grades'] as $code => $count)
                                            <span class="mr-2">{{ $code }}: {{ $count }}</span>
                                        @endforeach
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-card>
            @endif
        @else
            @if ($classResults->isEmpty())
                <x-empty-state :title="__('No class performance data matches')" />
            @else
                <x-card :padding="false">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead><tr class="text-left text-xs font-medium text-gray-500">
                            <th class="px-4 py-2">{{ __('Level') }}</th><th class="px-4 py-2">{{ __('Arm') }}</th>
                            <th class="px-4 py-2">{{ __('Students') }}</th><th class="px-4 py-2">{{ __('Average %') }}</th>
                            <th class="px-4 py-2">{{ __('Grade distribution') }}</th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($classResults as $row)
                                <tr>
                                    <td class="px-4 py-2">{{ $row['level_name'] }}</td>
                                    <td class="px-4 py-2">{{ $row['arm_name'] }}</td>
                                    <td class="px-4 py-2">{{ $row['student_count'] }}</td>
                                    <td class="px-4 py-2">{{ $row['average_percentage'] }}</td>
                                    <td class="px-4 py-2 text-xs text-gray-500">
                                        @foreach ($row['grades'] as $code => $count)
                                            <span class="mr-2">{{ $code }}: {{ $count }}</span>
                                        @endforeach
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-card>
            @endif
        @endif
    </div>
</x-layouts.authenticated>
