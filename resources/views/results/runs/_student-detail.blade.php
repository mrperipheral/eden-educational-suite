@php
    $fmt = fn ($n) => $n === null ? '—' : rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    $subjects = $subjectResultsByStudent->get($result->student_id, collect());
@endphp

<div class="space-y-4">
    @if ($result->hasAttendanceData())
        <p class="text-xs text-gray-500">
            {{ __('Attendance: :present / :opened days present (:pct%)', [
                'present' => $result->days_present, 'opened' => $result->days_school_opened, 'pct' => $fmt($result->attendance_percentage),
            ]) }}
        </p>
    @endif

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100 text-xs">
            <thead>
                <tr class="text-left font-medium text-gray-500">
                    <th class="py-1.5 pr-3">{{ __('Subject') }}</th>
                    <th class="py-1.5 pr-3">{{ __('Breakdown') }}</th>
                    <th class="py-1.5 pr-3">{{ __('%') }}</th>
                    <th class="py-1.5 pr-3">{{ __('Grade') }}</th>
                    @if ($run->ranking_enabled) <th class="py-1.5 pr-3">{{ __('Pos.') }}</th> @endif
                    <th class="py-1.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($subjects as $subjectResult)
                    <tr>
                        <td class="py-1.5 pr-3 font-medium text-gray-900">
                            {{ $subjectResult->subject?->name }}
                            @if ($subjectResult->is_adjusted) <x-badge variant="brand">{{ __('Adjusted') }}</x-badge> @endif
                        </td>
                        <td class="py-1.5 pr-3 text-gray-500">
                            {{ $subjectResult->components->map(fn ($c) => $c->category_name_snapshot.' '.$fmt($c->score_percentage).'%')->implode(' · ') }}
                        </td>
                        <td class="py-1.5 pr-3">{{ $fmt($subjectResult->percentage) }}</td>
                        <td class="py-1.5 pr-3">{{ $subjectResult->grade_code_snapshot ?? '—' }}</td>
                        @if ($run->ranking_enabled)
                            <td class="py-1.5 pr-3">{{ $subjectResult->subject_position ?? '—' }}</td>
                        @endif
                        <td class="py-1.5">
                            @if ($run->requiresAdjustment() && $canAdjust)
                                <details class="inline-block">
                                    <summary class="cursor-pointer text-brand-600 hover:text-brand-700">{{ __('Adjust') }}</summary>
                                    <div class="mt-2 w-72 rounded-md border border-gray-200 bg-white p-3">
                                        @foreach ($subjectResult->adjustments as $adj)
                                            <div class="mb-2 border-b border-gray-100 pb-2 text-xs">
                                                <x-badge :variant="$adj->status->badgeVariant()">{{ $adj->status->label() }}</x-badge>
                                                {{ $fmt($adj->original_value) }}% → {{ $fmt($adj->adjusted_value) }}%
                                                <span class="text-gray-400">— {{ $adj->reason }}</span>
                                                @if ($adj->isPending())
                                                    <div class="mt-1 flex gap-2">
                                                        <form method="POST" action="{{ route('results.adjustments.apply', [$run->id, $adj->id]) }}">@csrf
                                                            <x-button type="submit" size="sm">{{ __('Apply') }}</x-button>
                                                        </form>
                                                        <form method="POST" action="{{ route('results.adjustments.reject', [$run->id, $adj->id]) }}">@csrf
                                                            <x-button type="submit" size="sm" variant="ghost">{{ __('Reject') }}</x-button>
                                                        </form>
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                        <form method="POST" action="{{ route('results.adjustments.store', [$run->id, $subjectResult->id]) }}" class="space-y-2">
                                            @csrf
                                            <x-input name="adjusted_value" type="number" step="0.01" min="0" max="100" :label="__('New %')" required />
                                            <x-input name="reason" :label="__('Reason')" required />
                                            <x-button type="submit" size="sm">{{ __('Propose') }}</x-button>
                                        </form>
                                    </div>
                                </details>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($canComment)
        <form method="PATCH" action="{{ route('results.runs.students.comment', [$run->id, $result->id]) }}" class="space-y-2">
            @csrf
            @method('PATCH')
            <div class="space-y-1">
                <label class="block text-xs font-medium text-gray-500">{{ __('Class teacher comment') }}</label>
                <textarea name="class_teacher_comment" rows="2"
                    class="block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">{{ old('class_teacher_comment', $result->class_teacher_comment) }}</textarea>
            </div>
            @if ($canManage)
                <div class="space-y-1">
                    <label class="block text-xs font-medium text-gray-500">{{ __('Principal comment') }}</label>
                    <textarea name="principal_comment" rows="2"
                        class="block w-full rounded-md border-0 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-brand-500">{{ old('principal_comment', $result->principal_comment) }}</textarea>
                </div>
            @elseif ($result->principal_comment)
                <p class="text-xs text-gray-500"><span class="font-medium">{{ __('Principal:') }}</span> {{ $result->principal_comment }}</p>
            @endif
            <x-button type="submit" size="sm">{{ __('Save comments') }}</x-button>
        </form>
    @endif
</div>
