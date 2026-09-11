@php
    $fmt = fn ($n) => $n === null ? '—' : rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
@endphp

<x-layouts.authenticated :title="__('Results')">
    <div class="space-y-6">
        @include('student._nav', ['active' => 'results'])

        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-gray-500">{{ $run->session?->name }} · {{ $run->period?->name }} · {{ $run->level?->name }}@if ($run->arm) — {{ $run->arm->name }} @endif</p>
            <x-button :href="route('student.report-cards.show', $run->id)" size="sm">{{ __('View report card') }}</x-button>
        </div>

        <x-card :padding="false">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs text-gray-500">
                            <th class="px-4 py-2 sm:px-6">{{ __('Subject') }}</th>
                            <th class="px-4 py-2 sm:px-6">{{ __('Breakdown') }}</th>
                            <th class="px-4 py-2 text-right sm:px-6">{{ __('%') }}</th>
                            <th class="px-4 py-2 sm:px-6">{{ __('Grade') }}</th>
                            <th class="px-4 py-2 sm:px-6">{{ __('Remark') }}</th>
                            @if ($run->ranking_enabled)
                                <th class="px-4 py-2 sm:px-6">{{ __('Position') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($subjectResults as $sr)
                            <tr>
                                <td class="px-4 py-2 font-medium text-gray-900 sm:px-6">{{ $sr->subject?->name }}</td>
                                <td class="px-4 py-2 text-gray-500 sm:px-6">
                                    {{ $sr->components->map(fn ($x) => $x->category_name_snapshot.' '.$fmt($x->raw_score).'/'.$fmt($x->raw_max_score))->implode(', ') }}
                                </td>
                                <td class="px-4 py-2 text-right text-gray-900 sm:px-6">{{ $fmt($sr->percentage) }}</td>
                                <td class="px-4 py-2 text-gray-900 sm:px-6">{{ $sr->grade_code_snapshot ?? '—' }}</td>
                                <td class="px-4 py-2 text-gray-500 sm:px-6">{{ $sr->grade_remark_snapshot }}</td>
                                @if ($run->ranking_enabled)
                                    <td class="px-4 py-2 text-gray-500 sm:px-6">{{ $sr->subject_position ?? '—' }}</td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-4 text-gray-400 sm:px-6">{{ __('No subjects recorded.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <x-card :title="__('Overall performance')">
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm sm:grid-cols-4">
                <dt class="text-gray-500">{{ __('Total') }}</dt>
                <dd class="text-gray-900">{{ $fmt($studentResult->total_percentage) }}</dd>
                <dt class="text-gray-500">{{ __('Average') }}</dt>
                <dd class="text-gray-900">{{ $fmt($studentResult->average_percentage) }}%</dd>
                @if ($run->ranking_enabled)
                    <dt class="text-gray-500">{{ __('Class position') }}</dt>
                    <dd class="text-gray-900">{{ $studentResult->position ?? '—' }}</dd>
                    <dt class="text-gray-500">{{ __('Class size') }}</dt>
                    <dd class="text-gray-900">{{ $studentResult->class_size }}</dd>
                @endif
            </dl>

            @if ($studentResult->class_teacher_comment || $studentResult->principal_comment)
                <div class="mt-4 space-y-2 border-t border-gray-100 pt-3 text-sm">
                    @if ($studentResult->class_teacher_comment)
                        <p><span class="font-medium text-gray-500">{{ __('Class teacher:') }}</span> {{ $studentResult->class_teacher_comment }}</p>
                    @endif
                    @if ($studentResult->principal_comment)
                        <p><span class="font-medium text-gray-500">{{ __('Principal:') }}</span> {{ $studentResult->principal_comment }}</p>
                    @endif
                </div>
            @endif
        </x-card>
    </div>
</x-layouts.authenticated>
