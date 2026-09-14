@php
    $selectClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500';
@endphp

<x-layouts.authenticated :title="__('CBT Reports')">
    <x-slot:actions>
        @can('reports.export')
            <x-button :href="route('reports.cbt.export', $filters)" size="sm" variant="secondary">{{ __('Export CSV') }}</x-button>
        @endcan
    </x-slot:actions>

    <div class="space-y-6">
        <p class="text-sm"><a href="{{ route('reports.index') }}" class="text-brand-600 hover:text-brand-700">{{ __('← Reports') }}</a></p>

        <x-alert variant="info">{{ __('Staff view — attempt scores are shown as recorded, independent of the exam\'s own result-release schedule to students.') }}</x-alert>

        <form method="GET" action="{{ route('reports.cbt.index') }}" class="flex flex-wrap items-end gap-2" x-data="{ levelId: '{{ $filters['level'] ?? '' }}' }">
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
                <label for="subject" class="block text-xs font-medium text-gray-700">{{ __('Subject') }}</label>
                <select id="subject" name="subject" class="{{ $selectClass }}">
                    <option value="">{{ __('All subjects') }}</option>
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject->id }}" @selected(($filters['subject'] ?? null) == $subject->id)>{{ $subject->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="space-y-1">
                <label for="status" class="block text-xs font-medium text-gray-700">{{ __('Status') }}</label>
                <select id="status" name="status" class="{{ $selectClass }}">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach (\App\Enums\ExaminationStatus::all() as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <x-button type="submit" size="sm" variant="secondary">{{ __('Filter') }}</x-button>
            @if (array_filter($filters))
                <x-button :href="route('reports.cbt.index')" size="sm" variant="ghost">{{ __('Clear') }}</x-button>
            @endif
        </form>

        @if ($examinations->isEmpty())
            <x-empty-state :title="__('No examinations match')" />
        @else
            <x-card :padding="false">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead><tr class="text-left text-xs font-medium text-gray-500">
                        <th class="px-4 py-2">{{ __('Title') }}</th><th class="px-4 py-2">{{ __('Class') }}</th>
                        <th class="px-4 py-2">{{ __('Status') }}</th><th class="px-4 py-2">{{ __('Attempts') }}</th>
                        <th class="px-4 py-2">{{ __('Completed') }}</th><th class="px-4 py-2">{{ __('Passed') }}</th>
                        <th class="px-4 py-2">{{ __('Average %') }}</th><th class="px-4 py-2"></th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($examinations as $exam)
                            <tr>
                                <td class="px-4 py-2">{{ $exam->title }}</td>
                                <td class="px-4 py-2 text-xs text-gray-500">{{ $exam->level?->name }}{{ $exam->arm ? ' — '.$exam->arm->name : '' }} · {{ $exam->subject?->name }}</td>
                                <td class="px-4 py-2"><x-badge :variant="$exam->status->badgeVariant()">{{ $exam->status->label() }}</x-badge></td>
                                <td class="px-4 py-2">{{ $exam->attempts_count }}</td>
                                <td class="px-4 py-2">{{ $exam->completed_attempts_count }}</td>
                                <td class="px-4 py-2">{{ $exam->passed_attempts_count }}</td>
                                <td class="px-4 py-2">{{ $exam->average_percentage !== null ? round($exam->average_percentage, 2) : '—' }}</td>
                                <td class="px-4 py-2"><x-button :href="route('reports.cbt.attempts', $exam->id)" size="sm" variant="ghost">{{ __('Attempts') }}</x-button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </x-card>
            {{ $examinations->links() }}
        @endif
    </div>
</x-layouts.authenticated>
